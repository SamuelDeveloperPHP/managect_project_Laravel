<?php

namespace App\Exceptions;

use RuntimeException;

/** O cronograma foi alterado por outra pessoa depois que o editor o carregou. */
class GanttRevisionConflict extends RuntimeException
{
    public function __construct(public readonly int $currentRevision)
    {
        parent::__construct('Outra pessoa alterou este cronograma enquanto você editava. Recarregue para ver as mudanças antes de salvar; assim nada é sobrescrito sem querer.');
    }
}
