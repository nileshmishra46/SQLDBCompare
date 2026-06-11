<?php

/**
 * Creates a PDO connection to Microsoft SQL Server.
 *
 * @param array $params Connection parameters:
 *                      - host: Server hostname or IP (e.g. localhost or 127.0.0.1\SQLEXPRESS)
 *                      - port: Port number (default 1433)
 *                      - dbname: Database name
 *                      - user: SQL Server username
 *                      - password: SQL Server password
 *                      - encrypt: Boolean (optional, default true)
 *                      - trust_server_certificate: Boolean (optional, default true)
 * @return PDO
 * @throws PDOException
 */
function getConnection(array $params): PDO {
    $host = $params['host'] ?? 'localhost';
    $port = !empty($params['port']) ? $params['port'] : '1433';
    $dbname = $params['dbname'] ?? '';
    
    // Build connection options for SQLSRV DSN
    // Format: sqlsrv:Server=serverName\instanceName,portNumber;Database=dbName
    $server = $host;
    if ($port !== '1433' && strpos($host, ',') === false) {
        $server .= ',' . $port;
    }
    
    $dsnParts = [
        "Server=" . $server,
        "Database=" . $dbname
    ];
    
    // Add encrypt option
    $encrypt = isset($params['encrypt']) ? (bool)$params['encrypt'] : true;
    $dsnParts[] = "Encrypt=" . ($encrypt ? 'yes' : 'no');
    
    // Add trust server certificate option
    $trustCert = isset($params['trust_server_certificate']) ? (bool)$params['trust_server_certificate'] : true;
    $dsnParts[] = "TrustServerCertificate=" . ($trustCert ? 'yes' : 'no');
    
    $dsn = "sqlsrv:" . implode(';', $dsnParts);
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
    ];
    
    return new PDO($dsn, $params['user'] ?? '', $params['password'] ?? '', $options);
}
