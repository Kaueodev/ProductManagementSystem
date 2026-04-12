<?php
/**
 * config/database.php
 * Conexão PDO com MySQL — GestProd Backend
 *
 * Altere as constantes abaixo para o seu ambiente.
 */

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'gestprod');
define('DB_USER', 'root');       // ← altere para seu usuário MySQL
define('DB_PASS', '123banco4bacate');           // ← altere para sua senha MySQL
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna uma instância PDO configurada.
 * Lança PDOException em caso de falha de conexão.
 */
function getConnection(): PDO
{
    static $pdo = null;          // conexão reutilizada na mesma requisição

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lança exceções
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // arrays associativos
            PDO::ATTR_EMULATE_PREPARES   => false,                     // prepared statements reais
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
