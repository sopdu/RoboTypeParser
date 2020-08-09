<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
class parserRobotyre{
    private function requerst($type, $method, $param = '', $return){
        $url = 'https://crm.robotyre.ru/api/'.$method.'/?';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($param, true));
        $res = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($res, true);
        //echo '<pre>'; print_r($return); '</pre><br />';
        if($return == false){
            return $result;
        } else {
            return $result[$return];
        }
    }
    private function getKey(){
        return $this->requerst(
            'post',
            'Authorization',
            [
                "login" => "info@rabbit-wheels.ru",
                "password" => "Yura-640005"
            ],
            'token'
        );
    }
    private function tableDB(){
        global $DB;
        if(empty($DB->Query("SHOW TABLES LIKE 'ils_setting'")->Fetch())){
            $DB->Query("
                create table ils_setting(
                    param text null,
                    value text null
                );      
            ");
        }
        return;
    }
    private function addZaprosId($zaprosId){
        global $DB;
        $DB->Query("
            insert into ils_setting (param, value) value ('robotare_zapros', '".$zaprosId."')
        ");
        return;
    }
    private function getZaprosId(){
        global $DB;
        return $DB->Query("
            select value from ils_setting where param = 'robotare_zapros'
        ")->Fetch()["value"];
    }
    private function dropZaprosId(){
        global $DB;
        $DB->Query("
            delete from ils_setting where param = 'robotare_zapros'
        ");
    }
    private function createZapros(){
        return $this->addZaprosId($this->requerst(
            'post',
            'GetProducts',
            [
                "token" => $this->getKey(),
                "date" => date('d.m.Y H:i:s', strtotime('now -6 hour'))
            ],
            'id'
        ));
    }
    private function getJson(){
        $res = $this->requerst(
            'post',
            'GetRequestResult',
            [
                "token" =>  $this->getKey(),
                "id"    =>  $this->getZaprosId()
            ],
            false
        );
        if($res["message"] == 'Запрос устарел'){
            $this->dropZaprosId();
            $this->createZapros();
            $this->getJson();
        }
        if($res["message"] == 'Запрос в обработке'){
            $this->getJson();
            sleep(15);
        }
        return $res;
    }
    public function main(){
        $this->tableDB();
        echo '<pre>'; print_r($this->getJson()); '</pre>';
        //$this->createZapros();

        //$this->addZaprosId(123);
        //echo '<pre>'; print_r($this->getZaprosId()); '</pre>';
        //$this->dropZaprosId();
        //echo '<pre>'; print_r($this->tableDB()); '</pre>';
        //echo 'test';
        return;
    }
}
$sopduRabotyre = new parserRobotyre();
?>