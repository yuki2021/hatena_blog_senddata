<?php

require('./config.php');
require('./vendor/autoload.php');

class GetAbstract {
    private $db;
    private $dbConfig;

    public function __construct() {
        $this->dbConfig = $GLOBALS['dbConfig'];
        $this->db = new \Hadi\Database();
        $this->db->connect($this->dbConfig);
    }

    public function __destruct() {
        $this->db->disconnect();
    }

    public function searchDB($url) {
        $data = apcu_fetch($url);
        if ($data === false) {
            $sql = 'SELECT b.abstract FROM `hatena_blog_data` AS a 
            LEFT JOIN `hatena_content_abstract` AS b 
            ON a.`id` = b.`hatena_blog_id`
            WHERE b.`abstract` IS NOT NULL AND
            a.`url` = :url
            ORDER BY a.`published` DESC';

            $sth = $this->db->pdo->prepare($sql);
            $sth->bindValue(':url', $url);
            $sth->execute();
            $data = $sth->fetch(PDO::FETCH_ASSOC);
            apcu_store($url, $data, 60 * 60 * 24); // 1日キャッシュ
        }

        return $data;
    }

    public function sendJSON($data) {
        header('Access-Control-Allow-Origin: https://www.ituki-yu2.net');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Content-Type: application/json');
        echo json_encode($data ? ['result' => $data['abstract']] : ['error' => 'No data found'],
            JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    public function getPOST() {
        // JSONデータの取得とデコード
        $json = file_get_contents('php://input');
        $data = json_decode($json, true); // trueで連想配列に変換
        $url = $data['url'] ?? '';

        if (!preg_match('/^https:\/\/www\.ituki-yu2\.net\/entry\/.*/', $url)) {
            $this->sendJSON(['error' => 'Invalid URL format']);
            exit;
        }

        return $url;
    }
}

$obj = new GetAbstract();
$url = $obj->getPOST();
$data = $obj->searchDB($url);
$obj->sendJSON($data);
