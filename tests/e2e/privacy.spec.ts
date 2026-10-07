import { expect, Page, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

const PASSWORD = 'e2e-senha-forte-123';
const ADMIN = 'e2e.admin@example.test';
const MEMBER = 'e2e.membro@example.test';
const PENDING = 'e2e.pendente@example.test';
const LEAVER = 'e2e.saiu@example.test';

async function login(page: Page, email: string, password = PASSWORD) {
    await page.goto('/login');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await page.locator('button[type=submit]').click();
}

test.describe.configure({ mode: 'serial' });

test('a política e os termos abrem sem login e o rodapé do login leva até eles', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('link', { name: 'Privacidade' }).click();
    await expect(page).toHaveURL(/\/privacidade$/);
    await expect(page.getByRole('heading', { name: 'Política de Privacidade', level: 1 })).toBeVisible();
    await expect(page.getByRole('link', { name: 'privacidade@example.test' }).first()).toBeVisible();
    await expect(page.getByText(/após 365 dias/)).toBeVisible();

    await page.getByRole('link', { name: 'Termos de Uso' }).first().click();
    await expect(page).toHaveURL(/\/termos$/);
    await expect(page.getByRole('heading', { name: 'Termos de Uso', level: 1 })).toBeVisible();
});

test('o cadastro público só vale com o aceite dos termos e registra a ciência', async ({ page }) => {
    await page.goto('/register');
    const accept = page.getByRole('checkbox', { name: /Política de Privacidade/ });
    await expect(accept).toBeVisible();
    await expect(accept).not.toBeChecked();
    await expect(page.getByRole('link', { name: 'Política de Privacidade' })).toHaveAttribute('href', /\/privacidade$/);

    await page.fill('#company_name', 'Empresa Cadastro E2E');
    await page.fill('#company_document', '11.222.333/0001-81');
    await page.fill('#name', 'Pessoa Cadastro');
    await page.fill('#cpf', '529.982.247-25');
    await page.fill('#email', 'cadastro.e2e@example.test');
    await page.fill('#password', 'cadastro-senha-forte-123');
    await page.fill('#password_confirmation', 'cadastro-senha-forte-123');

    // O navegador barra o envio sem o aceite (campo obrigatório).
    await page.getByRole('button', { name: 'Fazer cadastro' }).click();
    await expect(page).toHaveURL(/\/register$/);

    await accept.check();
    await page.getByRole('button', { name: 'Fazer cadastro' }).click();
    await page.waitForURL(/dashboard/);

    await page.goto('/profile');
    await expect(page.getByText(/Versão 2026-10, em /)).toBeVisible();
});

test('quem ainda não aceitou é levado ao aceite e só depois usa o sistema', async ({ page }) => {
    await login(page, PENDING);
    await page.waitForURL(/aceite-dos-termos/);
    await page.goto('/projects');
    await expect(page).toHaveURL(/aceite-dos-termos/);

    const button = page.getByRole('button', { name: 'Aceitar e continuar' });
    await expect(button).toBeDisabled();
    await page.getByRole('checkbox').check();
    await button.click();
    await page.waitForURL(/dashboard/);
    await page.goto('/projects');
    await expect(page).toHaveURL(/\/projects$/);
});

test('a pessoa baixa os próprios dados em JSON pelo Perfil', async ({ page }) => {
    await login(page, MEMBER);
    await page.waitForURL(/dashboard/);
    await page.goto('/profile');

    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'Baixar meus dados (JSON)' }).click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/^dados-trilha-\d+-\d{8}\.json$/);
    const data = JSON.parse(readFileSync((await download.path())!, 'utf8'));

    expect(data.titular.email).toBe(MEMBER);
    expect(data.projetos.map((p: { nome: string }) => p.nome)).toContain('Projeto E2E');
    expect(data.ciencia_dos_termos.versao).toBe('2026-10');
    expect(JSON.stringify(data)).not.toMatch(/password|two_factor_secret|\$2y\$/i);
});

test('o administrador exporta e apaga os dados de quem saiu, com a própria senha', async ({ browser, page }) => {
    await login(page, ADMIN);
    await page.waitForURL(/dashboard/);
    await page.goto('/company/users');

    const row = page.locator('div', { has: page.getByText(LEAVER, { exact: true }) }).filter({ has: page.getByRole('button', { name: 'Apagar dados' }) }).last();
    await expect(row.getByRole('link', { name: 'Exportar dados' })).toBeVisible();

    await row.getByRole('button', { name: 'Apagar dados' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByRole('button', { name: 'Apagar dados' })).toBeDisabled();

    // Senha errada: nada é apagado.
    await dialog.getByLabel('Sua senha, para confirmar').fill('senha-errada-123');
    await dialog.getByRole('button', { name: 'Apagar dados' }).click();
    await expect(dialog.locator('p.text-red-600')).toBeVisible();
    await expect(dialog.getByLabel('Sua senha, para confirmar')).toHaveValue('');
    await expect(page.getByText(LEAVER, { exact: true })).toBeVisible();

    await dialog.getByLabel('Sua senha, para confirmar').fill(PASSWORD);
    await dialog.getByRole('button', { name: 'Apagar dados' }).click();
    await expect(page.getByText(/Dados pessoais apagados/)).toBeVisible();
    await expect(page.getByText(LEAVER, { exact: true })).toHaveCount(0);

    // A pessoa não entra mais.
    const other = await (await browser.newContext()).newPage();
    await login(other, LEAVER);
    await expect(other).toHaveURL(/\/login/);
    await expect(other.locator('p.text-red-600')).toBeVisible();
});

test('a própria pessoa exclui a conta: os dados somem e o acesso termina', async ({ page }) => {
    await login(page, MEMBER);
    await page.waitForURL(/dashboard/);
    await page.goto('/profile');

    await page.getByRole('button', { name: 'Excluir conta e apagar meus dados' }).click();
    await page.getByPlaceholder('Digite sua senha').fill(PASSWORD);
    await page.getByRole('dialog').getByRole('button', { name: 'Excluir conta', exact: true }).click();
    await page.waitForURL(/127\.0\.0\.1:\d+\/$/);

    await login(page, MEMBER);
    await expect(page).toHaveURL(/\/login/);
});
