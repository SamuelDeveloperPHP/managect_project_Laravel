<?php

namespace Tests\Feature;

use App\Support\Gantt\TaskInput;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A validação enxuta (TaskInput::check) precisa se comportar exatamente como as regras de referência do Laravel
 * (TaskInput::rules). Este teste compara as duas em muitas entradas, inclusive as estranhas.
 */
class GanttTaskInputTest extends TestCase
{
    private function base(): array
    {
        return [
            'id' => 12, 'name' => 'Tarefa', 'code' => 'T1', 'description' => 'Texto', 'level' => 1, 'status' => 'STATUS_ACTIVE',
            'progress' => 40, 'start' => 1790000000000, 'end' => 1790086400000, 'duration' => 2, 'depends' => '1:2',
            'backlogItemId' => 5, 'collapsed' => false, 'startIsMilestone' => true, 'endIsMilestone' => false,
            'assigs' => [['resourceId' => '7', 'roleId' => 'responsible', 'effort' => 3600]],
        ];
    }

    /** @return array<string, array> */
    public static function variations(): array
    {
        $odd = [null, '', ' ', 'abc', '12', '12.5', 12, 12.0, 12.5, -1, 0, 1, true, false, '1', '0', [], [1], ['a' => 1], str_repeat('x', 191), str_repeat('x', 4001), 99999999999999, '1e3'];
        $cases = [];
        foreach (['id', 'name', 'code', 'description', 'level', 'status', 'progress', 'start', 'end', 'duration', 'depends', 'backlogItemId', 'collapsed', 'startIsMilestone', 'endIsMilestone', 'assigs'] as $field) {
            foreach ($odd as $i => $value) {
                $cases["$field #$i ".json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR)] = [$field, $value];
            }
            $cases["$field removido"] = [$field, '__remove__'];
        }
        foreach (['STATUS_DONE', 'STATUS_WAITING', 'STATUS_SUSPENDED', 'STATUS_FAILED', 'STATUS_UNDEFINED', 'STATUS_OUTRO'] as $status) {
            $cases["status $status"] = ['status', $status];
        }
        foreach ([[0, 20, 21, -1], [0, 100, 101, -1]] as $_) {
        }
        foreach (['level' => [0, 20, 21, -1], 'progress' => [0, 100, 101, -1], 'duration' => [1, 3650, 3651, 0]] as $field => $values) {
            foreach ($values as $value) {
                $cases["$field limite $value"] = [$field, $value];
            }
        }
        foreach ([[], [['resourceId' => 1]], [['roleId' => 'responsible']], [['resourceId' => 1, 'roleId' => 'inexistente']], [['resourceId' => 'x', 'roleId' => 'reviewer']],
            [['resourceId' => 1, 'roleId' => 'supporter', 'effort' => -1]], [['resourceId' => 1, 'roleId' => 'supporter', 'effort' => 31536000001]], [['resourceId' => 1, 'roleId' => 'supporter', 'effort' => '']],
            [['resourceId' => 1, 'roleId' => 'supporter', 'effort' => null]], ['x'], [null], array_fill(0, 20, ['resourceId' => 1, 'roleId' => 'reviewer']), array_fill(0, 21, ['resourceId' => 1, 'roleId' => 'reviewer'])] as $i => $assigs) {
            $cases["assigs variação $i"] = ['assigs', $assigs];
        }
        $cases['entrada inteira vazia'] = ['__all__', []];
        $cases['entrada nao-array'] = ['__all__', 'texto'];
        $cases['campo extra ignorado'] = ['extra', 'valor'];

        return $cases;
    }

    /** @dataProvider variations */
    #[DataProvider('variations')]
    public function test_lean_validation_matches_the_laravel_reference(string $field, mixed $value): void
    {
        $task = $this->base();
        if ($field === '__all__') {
            $task = $value;
        } elseif ($value === '__remove__') {
            unset($task[$field]);
        } else {
            $task[$field] = $value;
        }

        $laravel = Validator::make(is_array($task) ? $task : [], TaskInput::rules(), TaskInput::messages());
        [$clean, $error] = TaskInput::check($task);

        $this->assertSame($laravel->fails(), $error !== null, 'pass/fail diverge para '.json_encode($task, JSON_PARTIAL_OUTPUT_ON_ERROR).' | laravel: '.json_encode($laravel->errors()->all()).' | enxuto: '.json_encode($error));
        if ($error !== null) {
            $this->assertSame($laravel->errors()->first(), $error, 'a mensagem do primeiro erro diverge');
        } else {
            $this->assertEquals($laravel->validated(), $clean, 'os dados limpos divergem');
        }
    }
}
