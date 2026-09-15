<?php

declare(strict_types=1);

// 只向已迁移的空数据库导入演示副本，不覆盖现有用户数据。
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__).'/vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$connection = null;

try {
    if (($_ENV['APP_ENV'] ?? '') !== 'dev') {
        throw new RuntimeException('Usare APP_ENV=dev e un database locale dedicato alla prova.');
    }
    $params = (new DsnParser(['mysql' => 'pdo_mysql']))->parse($_ENV['DATABASE_URL'] ?? '');
    if (($params['driver'] ?? '') !== 'pdo_mysql') {
        throw new RuntimeException('La copia dimostrativa richiede MariaDB/MySQL.');
    }
    $connection = DriverManager::getConnection($params);
    $tables = $connection->createSchemaManager()->listTableNames();
    if (!in_array('shared_note_comment', $tables, true)) {
        throw new RuntimeException('Eseguire prima doctrine:migrations:migrate.');
    }
    foreach ($tables as $table) {
        if ($table === 'doctrine_migration_versions') {
            continue;
        }
        if ((int) $connection->fetchOne('SELECT COUNT(*) FROM '.$connection->quoteIdentifier($table)) > 0) {
            throw new RuntimeException('Il database contiene già dati. Importazione annullata: usare un database vuoto.');
        }
    }

    $lines = file(dirname(__DIR__).'/demo/demo.sql', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('File demo/demo.sql non trovato.');
    }
    $connection->beginTransaction();
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '--') || in_array($line, ['START TRANSACTION;', 'COMMIT;'], true)) {
            continue;
        }
        // 导出文件每行一条 INSERT；事务确保失败时不留下半份演示数据。
        if (!str_starts_with($line, 'INSERT INTO `') || !str_ends_with($line, ';')) {
            throw new RuntimeException('Formato del file dimostrativo non riconosciuto.');
        }
        $connection->executeStatement($line);
    }
    $connection->commit();
    echo "Dati dimostrativi importati. Account e percorso di prova: demo/README.md\n";
} catch (Throwable $error) {
    if ($connection !== null && $connection->isTransactionActive()) {
        $connection->rollBack();
    }
    // 不输出底层连接异常，避免将本机连接参数带入终端或截图。
    fwrite(STDERR, $error instanceof RuntimeException && !$error instanceof Doctrine\DBAL\Exception
        ? $error->getMessage()."\n"
        : "Importazione non riuscita. Controllare la connessione, le migrazioni e che il database sia vuoto.\n");
    exit(1);
}
