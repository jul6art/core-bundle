<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Performance\Profiler\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Jul6Art\CoreBundle\Performance\Profiler\QueryTracker;

final class PerformanceConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private readonly QueryTracker $tracker)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new PerformanceStatement(parent::prepare($sql), $this->tracker, $sql);
    }

    public function query(string $sql): Result
    {
        $handle = $this->tracker->start($sql);
        try {
            return parent::query($sql);
        } finally {
            $this->tracker->end($handle);
        }
    }

    public function exec(string $sql): int|string
    {
        $handle = $this->tracker->start($sql);
        try {
            return parent::exec($sql);
        } finally {
            $this->tracker->end($handle);
        }
    }

    /**
     * ⚠️ Les transactions COMPTENT, et c'est ce qui manquait.
     *
     * `beginTransaction()`, `commit()` et `rollBack()` ne passent ni par `query()`, ni par
     * `exec()`, ni par `prepare()` : sans ces trois surcharges, un `flush()` Doctrine — qui
     * encadre systématiquement ses écritures d'une transaction — coûtait deux allers-retours
     * invisibles. Sur une page faisant six écritures, le panneau annonçait 13 requêtes là où le
     * collecteur Doctrine en comptait 25 : les douze manquantes étaient les six START TRANSACTION
     * et les six COMMIT.
     *
     * Deux chiffres qui se contredisent, c'est un outil de mesure qu'on cesse de croire — d'où
     * l'alignement sur le vocabulaire exact du collecteur Doctrine (« START TRANSACTION »,
     * « COMMIT », « ROLLBACK »), qui rend les deux panneaux comparables ligne à ligne.
     */
    public function beginTransaction(): void
    {
        $handle = $this->tracker->start('START TRANSACTION');
        try {
            parent::beginTransaction();
        } finally {
            $this->tracker->end($handle);
        }
    }

    public function commit(): void
    {
        $handle = $this->tracker->start('COMMIT');
        try {
            parent::commit();
        } finally {
            $this->tracker->end($handle);
        }
    }

    public function rollBack(): void
    {
        $handle = $this->tracker->start('ROLLBACK');
        try {
            parent::rollBack();
        } finally {
            $this->tracker->end($handle);
        }
    }
}
