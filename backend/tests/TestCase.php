<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");
        if (! (($connection === 'sqlite' && $database === ':memory:') || ($connection === 'mysql' && $database === 'wisata_concurrency_test'))) {
            throw new \RuntimeException('Tests require SQLite :memory: or the isolated wisata_concurrency_test database. Application databases are forbidden.');
        }

        return $app;
    }
}
