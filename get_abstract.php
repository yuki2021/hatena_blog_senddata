<?php

require('./config.php');
require('./vendor/autoload.php');

class GetAbstract {
    private $db;
    private $dbConfig;

    public function __construct() {

        $this->dbConfig = $GLOBALS['dbConfig'];

        // DB接続クラス取得
        $this->db = new \Hadi\Database();
        $this->db->connect($this->dbConfig);
    }

    public function __destruct() {
        $this->db->disconnect();
    }

    /// 渡されたURLでDBを検索する。見つけたデータを返す
    public function searchDB($url) {
        // APCuにキャッシュがあればそれを返す
        $data = apcu_fetch($url);
        if ($data === false) {
            $sql = 'SELECT b.abstract FROM `hatena_blog_data` AS a 
            LEFT JOIN `hatena_content_abstract` AS b 
            ON a.`id` = b.`hatena_blog_id`
            where b.`abstract` IS NOT NULL AND
            a.`url` = :url
            order by a.`published` desc';
            
            //prepareによるクエリの実行準備
            $sth = $this->db->pdo->prepare($sql);

            //検索クエリの設定
            $sth -> bindValue(':url', $url);
            
            //検索クエリの実行
            $sth -> execute();

            //結果を配列で取得
            $data = $sth -> fetch(PDO::FETCH_ASSOC);

            // APCuにキャッシュする
            apcu_store($url, $data, 60 * 60 * 24);
        }

        return $data;
    }

    /// 見つけたデータをJSONとして送り返す
    public function sendJSON($data) {
        header('Access-Control-Allow-Origin:https://www.ituki-yu2.net');
        header("Content-type: text/xml;charset=utf-8");
        if(!empty($data['abstract'])) {
            echo json_encode(array('result' => $data['abstract']),
                JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        } else {
            echo json_encode(array('error' => 'error'),
                JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
    }

    /// POST変数からURLを抜き出して文字列で返す
    public function getPOST() {
        $url = $_POST['url'];
        return $url;
    }
}

$obj = new GetAbstract();
$url = $obj->getPOST();
$data = $obj->searchDB($url);
$obj->sendJSON($data);