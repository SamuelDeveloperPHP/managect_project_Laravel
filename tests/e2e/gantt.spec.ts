import { expect, Page, test } from '@playwright/test';

const PASSWORD = 'e2e-senha-forte-123';
const ADMIN = 'e2e.admin@example.test';
const OUTSIDER = 'e2e.fora@example.test';
const TIMELINE = '/projects/1/backlogs/1/timeline';
const API = '/api/projects/1/backlogs/1/gantt';

type ApiTask = { id: number; name: string; progress: number; depends: string; level: number; duration: number; start: number; end: number };

async function login(page: Page, email: string) {
    await page.goto('/login');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', PASSWORD);
    await page.locator('button[type=submit]').click();
    await page.waitForURL(/dashboard/);
}

async function openGantt(page: Page) {
    await page.goto(TIMELINE);
    await page.waitForFunction(() => (window as any).ge?.tasks?.length > 0);
}

/** O Gantt tem um botão Salvar oculto na barra interna; a pessoa usa o do cabeçalho da página. */
const saveButton = (page: Page) => page.locator('button:visible', { hasText: /^Salvar$/ });
const rows = (page: Page) => page.locator('tr.taskEditRow:not(.emptyRow)');
const cell = (page: Page, row: number, field: string) => rows(page).nth(row).locator(`input[name=${field}]`);

/** Edita um campo da grade como a pessoa faria: digita e sai do campo (é o blur que grava na tarefa). */
async function setField(page: Page, row: number, field: string, value: string) {
    // O editor repinta a grade logo depois de inserir/editar linhas; digitar numa linha que está prestes a ser
    // substituída perde o valor. Em máquina lenta (CI) isso acontece, então digita de novo até a tarefa em memória
    // refletir o valor: é o que uma pessoa faria ao ver o campo vazio.
    await expect(async () => {
        const input = cell(page, row, field);
        await input.fill(value, { timeout: 3000 });
        await input.blur();
        const stored = await page.evaluate(([index, prop]) => String((window as any).ge.tasks[index as number]?.[prop as string] ?? ''), [row, field]);
        expect(stored).toBe(value);
    }).toPass({ timeout: 20_000, intervals: [250, 500, 1000] });
    // Pausa curta de quem digita: o editor tem temporizadores que repintam a grade logo após cada edição.
    await page.waitForTimeout(300);
}

const taskCount = (page: Page) => page.evaluate(() => (window as any).ge.tasks.length as number);

/** Seleciona a linha, espera o editor marcá-la como atual e só então insere (clicar cedo demais não faz nada). */
async function insertBelow(page: Page, afterRow: number) {
    const before = await taskCount(page);
    await rows(page).nth(afterRow).locator('input[name=name]').click();
    await expect(rows(page).nth(afterRow)).toHaveClass(/rowSelected/);
    await page.getByTitle('Inserir tarefa abaixo').click();
    await expect.poll(() => taskCount(page)).toBe(before + 1);
    // A grade redesenha as linhas logo depois de inserir; digitar antes disso cairia numa linha descartada.
    await expect(rows(page).nth(afterRow + 1)).toHaveClass(/rowSelected/);
    await expect(rows(page).nth(afterRow)).not.toHaveClass(/rowSelected/);
}

/** Clica em Salvar e devolve o que o sistema mostrou: o diálogo de sucesso ou o de erro. */
async function save(page: Page): Promise<{ ok: boolean; message: string }> {
    await saveButton(page).click();
    const dialog = page.locator('.swal2-popup');
    await expect(dialog).toBeVisible();
    const ok = await dialog.locator('.swal2-success').isVisible().catch(() => false);
    const message = ((await dialog.locator('#swal2-html-container').textContent()) ?? '').trim();
    if (ok) await dialog.locator('.swal2-confirm').click();
    return { ok, message };
}

async function savedTasks(page: Page): Promise<ApiTask[]> {
    const response = await page.request.get(API);
    expect(response.ok()).toBeTruthy();
    return (await response.json()).project.tasks;
}

async function dismissDialog(page: Page, button: 'confirm' | 'cancel' = 'confirm') {
    await page.locator(`.swal2-popup .swal2-${button}`).click();
}

// Os testes compartilham o mesmo cronograma (cada um parte do que o anterior deixou), então rodam em ordem.
test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
    page.on('pageerror', (error) => { throw new Error(`Erro de JavaScript na página: ${error.message}`); });
});

test('o Gantt não registra o evento "unload" (os navegadores bloqueiam e o console mostra erro)', async ({ page }) => {
    // Registra todo tipo de evento que os scripts penduram em window/document, antes de qualquer script rodar.
    await page.addInitScript(() => {
        const seen: string[] = [];
        (window as any).__listenerTypes = seen;
        const original = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function (this: EventTarget, type: string, ...rest: [any, any?]) {
            if (this === window || this === document) seen.push(type);
            return original.call(this, type, ...rest);
        } as typeof original;
    });
    const consoleErrors: string[] = [];
    page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });

    await login(page, ADMIN);
    await openGantt(page);

    const types: string[] = await page.evaluate(() => (window as any).__listenerTypes);
    expect(types).not.toContain('unload');
    expect(consoleErrors.filter((text) => /permissions policy|unload/i.test(text))).toEqual([]);
});

test('o administrador cria tarefas, salva e elas continuam depois de recarregar', async ({ page }) => {
    await login(page, ADMIN);
    await openGantt(page);

    await setField(page, 0, 'name', 'Planejamento');
    await setField(page, 0, 'duration', '5');
    await insertBelow(page, 0);
    await setField(page, 1, 'name', 'Execução');
    await setField(page, 1, 'duration', '3');
    await insertBelow(page, 1);
    await setField(page, 2, 'name', 'Entrega');
    await setField(page, 2, 'duration', '2');

    const result = await save(page);
    expect(result.ok, result.message).toBe(true);

    const tasks = await savedTasks(page);
    expect(tasks.map((t) => t.name)).toEqual(['Planejamento', 'Execução', 'Entrega']);
    expect(tasks.every((t) => Number.isInteger(t.id) && t.id > 0)).toBe(true);

    await openGantt(page);
    await expect(rows(page)).toHaveCount(3);
    await expect(cell(page, 1, 'name')).toHaveValue('Execução');
    await expect(cell(page, 1, 'duration')).toHaveValue('3');
});

test('editar nome e progresso grava e a barra de progresso aparece', async ({ page }) => {
    await login(page, ADMIN);
    await openGantt(page);

    await setField(page, 0, 'name', 'Planejamento revisado');
    await setField(page, 0, 'progress', '40');
    const result = await save(page);
    expect(result.ok, result.message).toBe(true);

    const first = (await savedTasks(page))[0];
    expect(first.name).toBe('Planejamento revisado');
    expect(first.progress).toBe(40);

    await openGantt(page);
    await expect(cell(page, 0, 'progress')).toHaveValue('40');
    await expect(page.locator('.taskProgressSVG').first()).toBeVisible();
});

test('predecessora: grava, empurra a sucessora e recusa dependência circular', async ({ page }) => {
    await login(page, ADMIN);
    await openGantt(page);

    // "Entrega" (linha 3) passa a depender de "Execução" (linha 2). Uma filha não pode depender do próprio pai,
    // então a dependência é entre as duas irmãs.
    await setField(page, 2, 'depends', '2');
    const result = await save(page);
    expect(result.ok, result.message).toBe(true);

    const tasks = await savedTasks(page);
    expect(tasks[2].depends).toBe('2');
    expect(tasks[2].start, 'a sucessora não começa antes do fim da predecessora').toBeGreaterThanOrEqual(tasks[1].end);

    await openGantt(page);
    await expect(cell(page, 2, 'depends')).toHaveValue('2');

    // "Execução" passar a depender de "Entrega" fecharia um ciclo: o editor recusa e o cronograma salvo continua igual.
    const loop = cell(page, 1, 'depends');
    await loop.fill('3');
    await loop.blur();
    await page.waitForTimeout(500);
    expect(await page.evaluate(() => (window as any).ge.tasks[1].depends)).toBe('');
    const again = await save(page);
    expect(again.ok, again.message).toBe(true);
    expect((await savedTasks(page))[1].depends).toBe('');
});

test('excluir uma tarefa e salvar remove do cronograma', async ({ page }) => {
    await login(page, ADMIN);
    await openGantt(page);

    await expect(rows(page)).toHaveCount(3);
    await cell(page, 2, 'name').click();
    await page.getByTitle('Excluir').click();
    await expect(rows(page)).toHaveCount(2);
    const result = await save(page);
    expect(result.ok, result.message).toBe(true);

    expect((await savedTasks(page)).map((t) => t.name)).toEqual(['Planejamento revisado', 'Execução']);
    await openGantt(page);
    await expect(rows(page)).toHaveCount(2);
});

test('duas pessoas editando: quem salva depois recebe o aviso de conflito e nada é sobrescrito', async ({ browser }) => {
    const a = await (await browser.newContext()).newPage();
    const b = await (await browser.newContext()).newPage();
    await login(a, ADMIN);
    await login(b, ADMIN);
    await openGantt(a);
    await openGantt(b);

    await setField(a, 1, 'name', 'Execução (versão da pessoa A)');
    expect((await save(a)).ok).toBe(true);

    await setField(b, 1, 'name', 'Execução (versão da pessoa B)');
    const result = await save(b);
    expect(result.ok).toBe(false);
    await expect(b.locator('.swal2-title')).toHaveText(/alterado por outra pessoa/i);

    expect((await savedTasks(b))[1].name, 'a versão de A não pode ser sobrescrita').toBe('Execução (versão da pessoa A)');

    // "Continuar editando" mantém a tela; "Recarregar agora" traz a versão de A.
    await dismissDialog(b, 'cancel');
    await expect(cell(b, 1, 'name')).toHaveValue('Execução (versão da pessoa B)');
    const gridsBefore = await b.locator('#workSpace .gdfTable').count();
    await saveButton(b).click();
    await dismissDialog(b, 'confirm');
    await expect(cell(b, 1, 'name')).toHaveValue('Execução (versão da pessoa A)');
    // Regressão: recarregar criava um segundo editor sobre o primeiro e a grade aparecia duplicada.
    await expect(b.locator('#workSpace .gdfTable')).toHaveCount(gridsBefore);
    await expect(rows(b)).toHaveCount(2);

    // Já com a versão de A na tela, B consegue salvar normalmente (a revisão foi atualizada).
    await setField(b, 1, 'name', 'Execução (versão final de B)');
    const final = await save(b);
    expect(final.ok, final.message).toBe(true);
    expect((await savedTasks(b))[1].name).toBe('Execução (versão final de B)');
});

test('gestor que não participa do projeto vê o Gantt, mas não consegue salvar', async ({ page }) => {
    await login(page, OUTSIDER);
    await openGantt(page);

    await expect(rows(page)).toHaveCount(2);
    await expect(saveButton(page)).toHaveCount(0);

    // Mesmo forçando a chamada (com o token CSRF válido, como o Gantt faz), o servidor recusa por permissão.
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const response = await page.request.post(API, { data: { revision: 0, tasks: [] }, headers: { Accept: 'application/json', 'X-CSRF-Token': csrf ?? '' } });
    expect(response.status()).toBe(403);
});
