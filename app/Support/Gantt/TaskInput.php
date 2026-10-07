<?php

namespace App\Support\Gantt;

use Illuminate\Validation\Rule;

/**
 * Validação das tarefas enviadas pelo editor do Gantt.
 *
 * O validador do Laravel leva ~2,5 ms por tarefa (350 tarefas ≈ 1 s por salvamento), então o caminho quente usa
 * {@see self::check()}, uma versão enxuta com o MESMO contrato. {@see self::rules()} e {@see self::messages()} são a
 * definição de referência no formato do Laravel; o teste de equivalência (GanttTaskInputTest) compara as duas
 * implementações em dezenas de entradas para garantir que nunca divirjam.
 */
class TaskInput
{
    public const STATUSES = ['STATUS_ACTIVE', 'STATUS_DONE', 'STATUS_WAITING', 'STATUS_SUSPENDED', 'STATUS_FAILED', 'STATUS_UNDEFINED'];

    public const ROLES = ['responsible' => 'Responsável', 'supporter' => 'Apoiador', 'reviewer' => 'Revisor'];

    /** Definição de referência (formato Laravel) das regras de UMA tarefa. */
    public static function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:190'],
            'code' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:4000'],
            'level' => ['nullable', 'integer', 'between:0,20'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'progress' => ['nullable', 'integer', 'between:0,100'],
            'start' => ['required', 'numeric', 'min:0'],
            'end' => ['required', 'numeric', 'min:0'],
            'duration' => ['nullable', 'integer', 'between:1,3650'],
            'depends' => ['nullable', 'string', 'max:255'],
            'backlogItemId' => ['nullable', 'integer'],
            'collapsed' => ['nullable', 'boolean'],
            'startIsMilestone' => ['nullable', 'boolean'],
            'endIsMilestone' => ['nullable', 'boolean'],
            'assigs' => ['nullable', 'array', 'max:20'],
            'assigs.*.resourceId' => ['required_with:assigs', 'integer'],
            'assigs.*.roleId' => ['required_with:assigs', 'string', Rule::in(array_keys(self::ROLES))],
            'assigs.*.effort' => ['nullable', 'integer', 'between:0,31536000000'],
        ];
    }

    public static function messages(): array
    {
        return [
            'id.integer' => self::M_ID,
            'level.*' => self::M_LEVEL,
            'progress.*' => self::M_PROGRESS,
            'start.*' => self::M_START,
            'end.*' => self::M_END,
            'duration.*' => self::M_DURATION,
            'name.max' => self::M_NAME,
            'code.max' => self::M_CODE,
            'assigs.*' => self::M_ASSIG,
            'assigs.*.*.*' => self::M_ASSIG,
            'status.*' => self::M_STATUS,
            'depends.*' => self::M_DEPENDS,
            'backlogItemId.*' => self::M_ITEM,
            '*.*' => self::M_GENERIC,
            '*.*.*' => self::M_GENERIC,
            '*.*.*.*' => self::M_GENERIC,
        ];
    }

    private const M_ID = 'Identificador de tarefa inválido.';
    private const M_LEVEL = 'O nível da tarefa é inválido.';
    private const M_PROGRESS = 'O progresso deve ser um número inteiro entre 0 e 100.';
    private const M_START = 'A data inicial de uma tarefa é inválida.';
    private const M_END = 'A data final de uma tarefa é inválida.';
    private const M_DURATION = 'A duração deve ser de 1 a 3650 dias.';
    private const M_NAME = 'O nome da tarefa pode ter no máximo 190 caracteres.';
    private const M_CODE = 'O código da tarefa pode ter no máximo 80 caracteres.';
    private const M_ASSIG = 'Um responsável da tarefa é inválido.';
    private const M_STATUS = 'O status da tarefa é inválido.';
    private const M_DEPENDS = 'A lista de predecessoras é inválida.';
    private const M_ITEM = 'O vínculo com o item do backlog é inválido.';
    private const M_GENERIC = 'Os dados do cronograma são inválidos.';

    /**
     * Valida e limpa uma tarefa. Retorna [dados limpos, null] ou [null, mensagem do primeiro erro].
     * Só as chaves previstas nas regras (e presentes na entrada) passam para o resultado.
     *
     * @return array{0: ?array<string, mixed>, 1: ?string}
     */
    public static function check(mixed $raw): array
    {
        $task = is_array($raw) ? $raw : [];
        $clean = [];

        // id: inteiro (ou texto inteiro). Ids temporários do editor já chegam como null.
        if (self::present($task, 'id')) {
            if (self::asInt($task['id']) === null) {
                return [null, self::M_ID];
            }
        }
        self::keep($task, $clean, 'id');

        foreach ([['name', 190, self::M_NAME], ['code', 80, self::M_CODE], ['description', 4000, self::M_GENERIC]] as [$key, $max, $message]) {
            if (self::present($task, $key)) {
                if (! is_string($task[$key])) {
                    return [null, self::M_GENERIC]; // falhou a regra "string" (sem mensagem própria)
                }
                if (mb_strlen($task[$key]) > $max) {
                    return [null, $message];
                }
            }
            self::keep($task, $clean, $key);
        }

        foreach ([['level', 0, 20, self::M_LEVEL], ['progress', 0, 100, self::M_PROGRESS]] as [$key, $min, $max, $message]) {
            if (self::present($task, $key) && ! self::intBetween($task[$key], $min, $max)) {
                return [null, $message];
            }
            self::keep($task, $clean, $key);
        }

        if (self::present($task, 'status') && ! (is_string($task['status']) && in_array($task['status'], self::STATUSES, true))) {
            return [null, self::M_STATUS];
        }
        self::keep($task, $clean, 'status');

        foreach (['start' => self::M_START, 'end' => self::M_END] as $key => $message) {
            if (! self::present($task, $key) || ! is_numeric($task[$key]) || $task[$key] + 0 < 0) {
                return [null, $message];
            }
            $clean[$key] = $task[$key];
        }

        if (self::present($task, 'duration') && ! self::intBetween($task['duration'], 1, 3650)) {
            return [null, self::M_DURATION];
        }
        self::keep($task, $clean, 'duration');

        if (self::present($task, 'depends') && (! is_string($task['depends']) || mb_strlen($task['depends']) > 255)) {
            return [null, self::M_DEPENDS];
        }
        self::keep($task, $clean, 'depends');

        if (self::present($task, 'backlogItemId') && self::asInt($task['backlogItemId']) === null) {
            return [null, self::M_ITEM];
        }
        self::keep($task, $clean, 'backlogItemId');

        foreach (['collapsed', 'startIsMilestone', 'endIsMilestone'] as $key) {
            if (self::present($task, $key) && ! in_array($task[$key], [true, false, 1, 0, '1', '0'], true)) {
                return [null, self::M_GENERIC];
            }
            self::keep($task, $clean, $key);
        }

        if (self::present($task, 'assigs')) {
            if (! is_array($task['assigs']) || count($task['assigs']) > 20) {
                return [null, self::M_ASSIG];
            }
            $assigs = [];
            foreach ($task['assigs'] as $index => $assig) {
                $assig = is_array($assig) ? $assig : [];
                if (! self::present($assig, 'resourceId') || self::asInt($assig['resourceId']) === null
                    || ! self::present($assig, 'roleId') || ! is_string($assig['roleId']) || ! array_key_exists($assig['roleId'], self::ROLES)
                    || (self::present($assig, 'effort') && ! self::intBetween($assig['effort'], 0, 31_536_000_000))) {
                    return [null, self::M_ASSIG];
                }
                $entry = ['resourceId' => $assig['resourceId'], 'roleId' => $assig['roleId']];
                if (array_key_exists('effort', $assig)) {
                    $entry['effort'] = $assig['effort'];
                }
                $assigs[$index] = $entry;
            }
            $clean['assigs'] = $assigs;
        } elseif (array_key_exists('assigs', $task)) {
            $clean['assigs'] = $task['assigs'];
        }

        return [$clean, null];
    }

    /** Campo informado e não nulo (o middleware transforma "" em null; "nullable" aceita ambos). */
    private static function present(array $data, string $key): bool
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return false;
        }

        // Como o Laravel: texto vazio (ou só espaços) conta como "não informado" nas regras nullable.
        return ! (is_string($data[$key]) && trim($data[$key]) === '');
    }

    private static function keep(array $task, array &$clean, string $key): void
    {
        if (array_key_exists($key, $task)) {
            $clean[$key] = $task[$key];
        }
    }

    /** Equivalente à regra "integer" do Laravel (filter_var com FILTER_VALIDATE_INT). */
    private static function asInt(mixed $value): ?int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int === false ? null : $int;
    }

    private static function intBetween(mixed $value, int $min, int $max): bool
    {
        $int = self::asInt($value);

        return $int !== null && $int >= $min && $int <= $max;
    }
}
