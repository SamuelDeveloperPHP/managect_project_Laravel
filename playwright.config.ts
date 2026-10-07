import { defineConfig } from '@playwright/test';

const port = process.env.E2E_PORT ?? '8010';

// Testes de interface do Trilha+. Localmente usam o Chrome instalado (nada para baixar);
// na CI o runner do GitHub já traz o Chrome. Rode com: npm run test:e2e (precisa de `npm run build` antes).
export default defineConfig({
    testDir: './tests/e2e',
    // O banco é um só e os testes alteram o mesmo cronograma: um de cada vez, em ordem.
    workers: 1,
    fullyParallel: false,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        channel: 'chrome',
        locale: 'pt-BR',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'node tests/e2e/server.mjs',
        url: `http://127.0.0.1:${port}/login`,
        reuseExistingServer: false,
        timeout: 120_000,
        env: { E2E_PORT: port },
    },
});
