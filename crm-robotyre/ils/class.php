<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

/**
 * Class parserRobotyre
 */
class parserRobotyre{
    /**
     * @param $data
     * @param string $title
     * @return false|void
     */
    public function Log($data, $title = ''){
        define('DEBUG_FILE_NAME', date('Y-m-d').'.log');
        if(!DEBUG_FILE_NAME){ return false; }
        $log = "\n------------------------\n";
        $log .= date("Y.m.d G:i:s")."\n";
        #$log .= $this->GetUser()."\n";
        $log .= (strlen($title) > 0 ? $title : 'DEBUG')."\n";
        $log .= print_r($data, 1);
        $log .= "\n------------------------\n";
        file_put_contents(__DIR__."/".DEBUG_FILE_NAME, $log, FILE_APPEND);
        return;
    }

    /**
     * @param $value
     */
    public function dump($value){
        $filePath = $_SERVER["DOCUMENT_ROOT"].'/crm-robotyre/ils/ilsDump.txt';
        $file = fopen($filePath, "w");
        fwrite($file, print_r($value, 1));
        #fclose();
        return;
    }

    /**
     *
     */
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

    /**
     * @param $param
     * @param $value
     * @return CDBResult|false
     */
    private function tableAdd($param, $value){
        global $DB;
        if($value == 'NOW()'){
            return $DB->Query("
                insert into ils_setting (param, value) value ('".$param."', NOW())
            ");
        } else {
            return $DB->Query("
                insert into ils_setting (param, value) value ('".$param."', '".$value."')
            ");
        }
    }

    /**
     * @param $param
     * @return CDBResult|false
     */
    private function tableGet($param){
        global $DB;
        return $DB->Query("
            select value from ils_setting where param = '".$param."'
        ")->Fetch()["value"];
    }

    /**
     * @param $param
     * @return CDBResult|false
     */
    private function tableDrop($param){
        global $DB;
        return $DB->Query("
            delete from ils_setting where param = '".$param."'
        ");
    }

    /**
     * @param $method
     * @param string $param
     * @param $return
     * @return mixed
     */
    private function requerstKey($method, $param = '', $return){
        $url = 'https://crm.robotyre.ru/api/'.$method.'/';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($param, true));
        $res = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($res, true);
        if($return == false){
            return $result;
        } else {
            return $result[$return];
        }
    }

    /**
     * @return mixed
     */
    private function getKey(){
        return $this->requerstKey(
            'Authorization',
            [
                "login" => "info@rabbit-wheels.ru",
                "password" => "Yura-640005"
            ],
            'token'
        );
    }

    /**
     * @param $method
     * @param $param
     * @return mixed
     */
    private function requerst($method, $param){
        $url = 'https://crm.robotyre.ru/api/'.$method;
        $options = [
            "http"  => [
                "header"    =>  'Content-Type: application/x-www-form-urlencoded\r\n',
                "method"    =>  'POST',
                "content"   =>  http_build_query($param)
            ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return json_decode($result, true);
    }

    /**
     *
     */
    private function createZapros(){
        return $this->tableAdd(
            'robotare_zapros',
            $this->requerst(
                'GetProducts',
                [
                    "token" => $this->getKey(),
                    "date"  => date('d.m.Y H:i:s', strtotime('now -2 hour'))
                ],
                false
            )["id"]
        );
    }

    /**
     * @return mixed
     */
    private function getItems(){
        $zapros = $this->requerst(
            'GetRequestResult',
            [
                "token" =>  $this->getKey(),
                "id"    =>  $this->tableGet('robotare_zapros')
            ]
        );
        if($zapros["message"] == 'Запрос в обработке'){
            sleep(15);
            $this->getItem();
        }
        return$zapros;
    }

    /**
     * @param $type
     * @param $propertyCode
     * @return mixed
     */
    private function getProperty($type, $propertyCode){
        if($type == 'tire'){
            $iblock = [2, 10];
        } else {
            $iblock = 3;
        }
        $zapros = CIBlockPropertyEnum::GetList(
            [
                "IBLOCK_ID" =>  $iblock,
                "CODE"      =>  $propertyCode
            ]
        );
        while ($row = $zapros->Fetch()){
            $result[$row["VALUE"]] = $row["ID"];
        }
        return $result;
    }
    private function seveImg($robotyre_id){
        $img = $_SERVER["DOCUMENT_ROOT"] . '/crm-robotyre/ils_images/tmp/'.$robotyre_id.'.jpg';
        file_put_contents($img, file_get_contents('https://crm.robotyre.ru/Api/ProductImage/'.$robotyre_id));
        return CFile::MakeFileArray($img);
    }
    /**
     * @param $type
     * @param $robotyre_id
     * @return array
     */
    private function getItem($type, $robotyre_id){
        if($type == 'tire'){
            $iblock = [2, 10];
            $select = [
                "ID", "ACTIVE", "DETAIL_PICTURE",
                "PROPERTY_BRAND",
                "PROPERTY_WIDTH",
                "PROPERTY_STOCK",
                "PROPERTY_DELETE",
                "PROPERTY_MIIIX_ID",
                "PROPERTY_STUD",
                "PROPERTY_AVAILABLE",
                "PROPERTY_SHORT_NAME",
                "PROPERTY_HOMOLOGATION",
                "PROPERTY_STOCK_CITY",
                "PROPERTY_DELIVERY_DAY",
                "PROPERTY_AVITO_TEXT",
                "PROPERTY_HEIGHT",
                "PROPERTY_DIAMETR",
                "PROPERTY_SEASON",
                "PROPERTY_RUN_FLAT",
                "PROPERTY_SPEED_INDEX",
                "PROPERTY_LOAD_INDEX",
                "PROPERTY_TYPE_AUTO",
                "PROPERTY_ROBOTYRE",
                "PROPERTY_C",
                "PROPERTY_STATUS",
                "PROPERTY_AXIS",
                "PROPERTY_UPDATE",
                "PROPERTY_MODEL",
                "PROPERTY_CODE_PROVIDER",
                "PROPERTY_MARK_UP_ID",
                "PROPERTY_PROVIDER"
            ];
        } else {
            $iblock = 3;
            $select = [
                "ID", "ACTIVE", "DETAIL_PICTURE",
                "PROPERTY_TYPE",
                "PROPERTY_BRAND",
                "PROPERTY_ARTICLE",
                "PROPERTY_AVAILABLE",
                "PROPERTY_SHORT_NAME",
                "PROPERTY_DELETE",
                "PROPERTY_STOCK_ID",
                "PROPERTY_STOCK_CITY",
                "PROPERTY_COLOR",
                "PROPERTY_MODEL",
                "PROPERTY_DELIVERY_DAY",
                "PROPERTY_AVITO_TEXT",
                "PROPERTY_ET",
                "PROPERTY_DIA",
                "PROPERTY_WIDTH",
                "PROPERTY_DIAMETR",
                "PROPERTY_PCD",
                "PROPERTY_ROBOTYRE",
                "PROPERTY_STATUS",
                "PROPERTY_UPDATE",
                "PROPERTY_CODE_PROVIDER",
                "PROPERTY_MARK_UP_ID",
                "PROPERTY_PROVIDER"
            ];
        }
        $zapros = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID"             =>  $iblock,
                "PROPERTY_ROBOTYRE_ID"  =>  $robotyre_id
            ],
            false,
            false,
            $select
        );
        while ($row = $zapros->Fetch()){
            if($type == 'tire') {
                $result = [
                    "ID"                =>  $row["ID"],
                    "ACTIVE"            =>  $row["ACTIVE"],
                    "DETAIL_PICTURE"    =>  $row["DETAIL_PICTURE"],
                    "PROPERTY"          =>  [
                        "BRAND"         =>  $this->getProperty($type, $row["PROPERTY_BRAND_VALUE"]),
                        "WIDTH"         =>  $this->getProperty($type, $row["PROPERTY_WIDTH_VALUE"]),
                        "STOCK"         =>  $row["PROPERTY_STOCK_VALUE"],
                        "DELETE"        =>  $row["PROPERTY_DELETE_VALUE"],
                        "MIIIX_ID"      =>  $this->getProperty($type, $row["PROPERTY_MIIIX_ID_VALUE"]),
                        "STUD"          =>  $this->getProperty($type, $row["PROPERTY_STUD_VALUE"]),
                        "AVAILABLE"     =>  $row["PROPERTY_AVAILABLE_VALUE"],
                        "SHORT_NAME"    =>  $row["PROPERTY_SHORT_NAME_VALUE"],
                        "HOMOLOGATION"  =>  $this->getProperty($type, $row["PROPERTY_HOMOLOGATION_VALUE"]),
                        "STOCK_CITY"    =>  $row["PROPERTY_STOCK_CITY_VALUE"],
                        "DELIVERY_DAY"  =>  $row["PROPERTY_DELIVERY_DAY_VALUE"],
                        "AVITO_TEXT"    =>  $row["PROPERTY_AVITO_TEXT_VALUE"],
                        "HEIGHT"        =>  $this->getProperty($type, $row["PROPERTY_HEIGHT_VALUE"]),
                        "DIAMETR"       =>  $this->getProperty($type, $row["PROPERTY_DIAMETR_VALUE"]),
                        "SEASON"        =>  $this->getProperty($type, $row["PROPERTY_SEASON_VALUE"]),
                        "RUN_FLAT"      =>  $this->getProperty($type, $row["PROPERTY_RUN_FLAT_VALUE"]),
                        "SPEED_INDEX"   =>  $this->getProperty($type, $row["PROPERTY_SPEED_INDEX_VALUE"]),
                        "LOAD_INDEX"    =>  $this->getProperty($type, $row["PROPERTY_LOAD_INDEX_VALUE"]),
                        "TYPE_AUTO"     =>  $this->getProperty($type, $row["PROPERTY_TYPE_AUTO_VALUE"]),
                        "ROBOTYRE"      =>  $row["PROPERTY_ROBOTYRE_VALUE"],
                        "C"             =>  $this->getProperty($type, $row["PROPERTY_C_VALUE"]),
                        "STATUS"        =>  $row["PROPERTY_STATUS_VALUE"],
                        "AXIS"          =>  $this->getProperty($type, $row["PROPERTY_AXIS_VALUE"]),
                        "UPDATE"        =>  $this->getProperty($type, $row["PROPERTY_UPDATE_VALUE"]),
                        "MODEL"         =>  $row["PROPERTY_MODEL_VALUE"],
                        "CODE_PROVIDER" =>  $row["PROPERTY_CODE_PROVIDER_VALUE"],
                        "MARK_UP_ID"    =>  $row["PROPERTY_MARK_UP_ID_VALUE"],
                        "PROVIDER"      =>  $row["PROPERTY_PROVIDER_VALUE"]
                    ]
                ];
            } else {
                $result = [
                    "ID"                =>  $row["ID"],
                    "ACTIVE"            =>  $row["ACTIVE"],
                    "DETAIL_PICTURE"    =>  $row["DETAIL_PICTURE"],
                    "PROPERTY"          =>  [
                        "TYPE"          =>  $this->getProperty($type, $row["PROPERTY_TYPE_VALUE"]),
                        "BRAND"         =>  $this->getProperty($type, $row["PROPERTY_BRAND_VALUE"]),
                        "ARTICLE"       =>  $row["PROPERTY_ARTICLE_VALUE"],
                        "AVAILABLE"     =>  $row["PROPERTY_AVAILABLE_VALUE"],
                        "SHORT_NAME"    =>  $row["PROPERTY_SHORT_NAME_VALUE"],
                        "DELETE"        =>  $row["PROPERTY_DELETE_VALUE"],
                        "STOCK_ID"      =>  $row["PROPERTY_STOCK_ID_VALUE"],
                        "STOCK_CITY"    =>  $row["PROPERTY_STOCK_CITY_VALUE"],
                        "COLOR"         =>  $row["PROPERTY_COLOR_VALUE"],
                        "MODEL"         =>  $row["PROPERTY_MODEL_VALUE"],
                        "DELIVERY_DAY"  =>  $row["PROPERTY_DELIVERY_DAY_VALUE"],
                        "AVITO_TEXT"    =>  $row["PROPERTY_AVITO_TEXT_VALUE"],
                        "ET"            =>  $this->getProperty($type, $row["PROPERTY_ET_VALUE"]),
                        "DIA"           =>  $this->getProperty($type, $row["PROPERTY_DIA_VALUE"]),
                        "WIDTH"         =>  $this->getProperty($type, $row["PROPERTY_WIDTH_VALUE"]),
                        "DIAMETR"       =>  $this->getProperty($type, $row["PROPERTY_DIAMETR_VALUE"]),
                        "PCD"           =>  $this->getProperty($type, $row["PROPERTY_PCD_VALUE"]),
                        "ROBOTYRE"      =>  $row["PROPERTY_ROBOTYRE_VALUE"],
                        "STATUS"        =>  $row["PROPERTY_STATUS_VALUE"],
                        "UPDATE"        =>  $this->getProperty($type, $row["PROPERTY_UPDATE_VALUE"]),
                        "CODE_PROVIDER" =>  $row["PROPERTY_CODE_PROVIDER_VALUE"],
                        "MARK_UP_ID"    =>  $row["PROPERTY_MARK_UP_ID_VALUE"],
                        "PROVIDER"      =>  $row["PROPERTY_PROVIDER_VALUE"]
                    ]
                ];
            }
        }
        return $result;
    }

    /**
     * @param $type
     * @param $data
     */
    private function updateItem($type, $data){
        $element = new CIBlockElement;
        $product = new CCatalogProduct();
        $itemSite = $this->getItem($type, $data["@id"]);
        if(!empty($itemSite)) {
            $propertyArray = $itemSite["PROPERTY"];
            if ($data["@quantity"] != $itemSite["PROPERTY"]["AVAILABLE"]) {
                if ($data["@quantity"] >= 2) {
                    $active = 'Y';
                } else {
                    $active = 'N';
                }
                $propertyArray["AVAILABLE"] = $data["@quantity"];
                $product->Update($itemSite["ID"], ["QUANTITY" => $data["@quantity"]]);
            }
            $priceZapros = CPrice::GetList(
                [],
                [
                    "PRODUCT_ID" => $itemSite["ID"],
                    "CATALOG_GROUP_ID" => 1
                ]
            )->Fetch();
            if ($priceZapros["PRICE"] != $data["@price"]) {
                CPrice::Update(
                    $priceZapros["ID"],
                    [
                        "CURRENCY" => "RUB",
                        "PRICE" => $data["@price"],
                        "CATALOG_GROUP_ID" => 1,
                        "PRODUCT_ID" => $itemSite["ID"]
                    ]
                );
            }
            if ($data["@presence"] != $itemSite["PROPERTY"]["STATUS"]) {
                $propertyArray["STATUS"] = $data["@presence"];
            }
            if(empty($itemSite["DETAIL_PICTURE"])){
                $adding = [
                    "ACTIVE"            => $active,
                    "PROPERTY_VALUES"   => $propertyArray
                ];
            } else {
                $adding = [
                    "ACTIVE"            => $active,
                    "DETAIL_PICTURE"    =>  $this->seveImg($data["@id"]),
                    "PROPERTY_VALUES"   => $propertyArray
                ];
            }
            $element->Update(
                $itemSite["ID"],
                $adding
            );
            $this->Log($itemSite["ID"], 'изменен товар в '.date('d-m-Y H:i:s'));
        }
        return;
    }

    /**
     *
     */
    public function main(){
        $this->tableDrop('start');
        //$this->tableAdd('start', 'NOW()');


        //foreach ($this->getItems()["products"]["tires"]["tire"] as $tire){
            //echo '<pre>'; print_r($tire); '</pre>';
            //if($tire["@id"] == 111341){
                //$this->updateItem('tire', $tire);
            //}
       // }
        /*
        foreach ($this->getItems()["products"]["disks"]["disk"] as $disk){

        }
        */
        echo 'main test';
        return;
    }
}
$sopduRabotyre = new parserRobotyre();
?>