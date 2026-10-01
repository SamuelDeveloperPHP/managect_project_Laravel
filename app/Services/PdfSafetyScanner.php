<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\Process\Process;

class PdfSafetyScanner
{
    /**
     * The configured validator must be a trusted local executable/wrapper which
     * parses the full PDF and emits JSON: {"valid":true,"has_forms":false,
     * "active_content":false,"encrypted":false}. Missing or inconclusive
     * scanners intentionally fail closed.
     */
    public function assertSafe(UploadedFile $file): void
    {
        $validator = config('security.pdf_validator_binary');
        $antivirus = config('security.antivirus_binary');
        if (! is_string($validator) || $validator === '' || ! is_executable($validator)
            || ! is_string($antivirus) || $antivirus === '' || ! is_executable($antivirus)) {
            throw new RuntimeException('O recebimento de documentos PDF está temporariamente indisponível: os verificadores de segurança não estão configurados.');
        }

        $path = $file->getRealPath();
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        $validation = $this->run([$validator, $path]);
        $result = json_decode($validation['stdout'], true);
        if ($validation['code'] !== 0 || ! is_array($result)
            || ($result['valid'] ?? false) !== true
            || ($result['has_forms'] ?? true) !== false
            || ($result['active_content'] ?? true) !== false
            || ($result['encrypted'] ?? true) !== false) {
            throw new RuntimeException('O PDF é inválido, contém formulários/conteúdo ativo, está criptografado ou não pôde ser analisado com segurança.');
        }

        $scan = $this->run([$antivirus, '--no-summary', '--stdout', '--', $path]);
        if ($scan['code'] !== 0) {
            throw new RuntimeException('O arquivo foi rejeitado pela verificação antivírus ou o verificador não conseguiu concluir a análise.');
        }
    }

    /** @param list<string> $command @return array{code:int,stdout:string} */
    private function run(array $command): array
    {
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        return ['code' => $process->getExitCode() ?? 1, 'stdout' => mb_substr($process->getOutput(), 0, 1024 * 1024)];
    }
}
