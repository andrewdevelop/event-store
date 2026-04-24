<?php

namespace Core\EventStore\Tests;

use Core\EventStore\Drivers\FilesystemAdapter;
use Illuminate\Support\Facades\Config;

class ConcurrencyTest extends TestCase
{
    protected string $testFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->testFilePath = __DIR__ . '/test_concurrent_events.json';
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
        parent::tearDown();
    }

    public function test_filesystem_adapter_concurrent_writes()
    {
        // Создадим скрипт, который мы будем запускать параллельно
        $scriptPath = __DIR__ . '/concurrent_writer.php';
        
        $scriptContent = <<<PHP
<?php
require __DIR__ . '/../vendor/autoload.php';
\$config = new \Illuminate\Config\Repository([
    'eventstore' => [
        'connections' => [
            'filesystem' => [
                'database' => '{$this->testFilePath}'
            ]
        ]
    ]
]);

\$adapter = new \Core\EventStore\Drivers\FilesystemAdapter(\$config);

\$pid = getmypid();
\$events = [];
for (\$i = 0; \$i < 50; \$i++) {
    \$events[] = ['aggregate_id' => '1', 'pid' => \$pid, 'iteration' => \$i];
}

\$adapter->commit(\$events);
PHP;
        file_put_contents($scriptPath, $scriptContent);

        $processes = [];
        $numProcesses = 10;
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"],  // stderr
        ];

        // Запускаем 10 процессов одновременно
        for ($i = 0; $i < $numProcesses; $i++) {
            $process = proc_open('php ' . $scriptPath, $descriptorspec, $pipes);
            if (is_resource($process)) {
                $processes[] = [
                    'process' => $process,
                    'pipes' => $pipes
                ];
            }
        }

        // Ждем завершения всех процессов
        foreach ($processes as $proc) {
            proc_close($proc['process']);
        }

        unlink($scriptPath);

        // Проверяем, что файл не поврежден и содержит ровно 500 событий (10 процессов по 50 событий)
        $this->assertFileExists($this->testFilePath);
        
        $contents = file($this->testFilePath);
        $this->assertCount($numProcesses * 50, $contents, "Все события от всех процессов должны быть записаны");
        
        foreach ($contents as $line) {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded, "Строка должна быть валидным JSON: " . $line);
            $this->assertArrayHasKey('aggregate_id', $decoded);
            $this->assertArrayHasKey('pid', $decoded);
            $this->assertArrayHasKey('iteration', $decoded);
        }
    }
}
