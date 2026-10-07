// Sobe um Trilha+ descartável para os testes de interface: banco SQLite próprio, recriado a cada execução.
// Não depende do .env local (as variáveis abaixo têm precedência), então roda igual na CI.
import { spawn, spawnSync } from 'node:child_process';
import { rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const port = process.env.E2E_PORT ?? '8010';
const database = resolve(root, 'database/e2e.sqlite');

const env = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    APP_KEY: 'base64:Y2ktZHVtbXkta2V5LW5vdC1hLXNlY3JldC0zMmJ5IXg=',
    APP_URL: `http://127.0.0.1:${port}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    TWO_FACTOR_REQUIRED: 'false',
    PHP_CLI_SERVER_WORKERS: '4',
};

rmSync(database, { force: true });
writeFileSync(database, '');

const artisan = (...args) => {
    const result = spawnSync('php', ['artisan', ...args], { cwd: root, env, stdio: 'inherit' });
    if (result.status !== 0) process.exit(result.status ?? 1);
};
artisan('migrate', '--force');
artisan('db:seed', '--class=Database\\Seeders\\E2eSeeder', '--force');

// Igual ao `php artisan serve`: roda a partir de public/ com o roteador do framework.
const router = resolve(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
const server = spawn('php', ['-S', `127.0.0.1:${port}`, router], { cwd: resolve(root, 'public'), env, stdio: 'inherit' });
const stop = () => { server.kill(); process.exit(0); };
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
server.on('exit', (code) => process.exit(code ?? 0));
