<?php
date_default_timezone_set("GMT");

$dir = dirname(dirname(__FILE__));
#根域名列表文件，如果自己的根域名不存在该文件中，可自行添加
$domainfile = $dir . DIRECTORY_SEPARATOR . "domain.ini";

/*
  $obj = new TxyDns(txyaccessKeyId, APPKEY, APPTOKEN);
  //显示所有域名
  $data = $obj->DomainList();
  if ($data["code"]!=0) {
  echo $data["message"] . "\n";
  }
  //可以增加同名的二条
  $data = $obj->RecordCreate("www3","TXT",rand(10,1000));
  $data = $obj->RecordCreate("www3","TXT",rand(10,1000));
  $data = $obj->RecordCreate("www3.www3","TXT",rand(10,1000));

  if ($data["code"]!=0) {
  echo $data["message"] . "\n";
  }

  //查看一个主机的所有txt 记录
  $data = $obj->RecordList("www3.www3","TXT");

  $data = $obj->RecordList("www3","TXT");
  $records = $data["data"]["records"];
  foreach ($records as $k=>$v) {
  //根据ID修改记录
  $data = $obj->RecordModify("www3", "TXT", rand(1000,2000), $v["id"]);
  //根据ID删除记录
  $obj->RecordDelete($v["id"]);
  }
 */

###### 代码运行
//php txydns.php add "www.yudadan.com" "k1" "v1"  AKIDwlPr7DUpLgpZBb4tlT0MWUHtIVXOJwxm mMkxzoTxOirrfJlFYfbS7g7792jEi5GG
# 第一个参数是 action，代表 (add/clean) 
# 第二个参数是域名 
# 第三个参数是主机名（第三个参数+第二个参数组合起来就是要添加的 TXT 记录）
# 第四个参数是 TXT 记录值
# 第五个参数是 APPKEY
# 第六个参数是 APPTOKEN

echo "域名 API 调用开始\n";


if (count($argv) < 7) {
    echo "参数有误\n";
    exit;
}

echo $argv[1] . "-" . $argv[2] . "-" . $argv[3] . "-" . $argv[4] . "-key-token\n"; # "-" . $argv[5] . "-" . $argv[6] . "\n";

$domainarray = TxyDns::getDomain($argv[2]);
$selfdomain = ($domainarray[0] == "") ? $argv[3] : $argv[3] . "." . $domainarray[0];
$obj = new TxyDns($argv[5], $argv[6], $domainarray[1]);

switch ($argv[1]) {
    case "clean":
        $data = $obj->RecordList($selfdomain, "TXT");
        if (isset($data["Response"]["Error"])) {
            echo "txy dns 记录获取失败-[" . $data["Response"]["Error"]["Code"] . "]" . $data["Response"]["Error"]["Message"] . "\n";
            exit;
        }
        if (isset($data["Response"]["RecordList"])) {
            $records = $data["Response"]["RecordList"];
            foreach ($records as $k => $v) {

                $data = $obj->RecordDelete($v["RecordId"]);

                if (isset($data["Response"]["Error"])) {
                    echo "txy dns 记录删除失败-[" . $data["Response"]["Error"]["Code"] . "]" . $data["Response"]["Error"]["Message"] . "\n";
                    exit;
                }
            }
        }

        break;

    case "add":
        $data = $obj->RecordCreate($selfdomain, "TXT", $argv[4]);
        if (isset($data["Response"]["Error"])) {
            echo "txy dns 记录添加失败-[" . $data["Response"]["Error"]["Code"] . "]" . $data["Response"]["Error"]["Message"] . "\n";
            exit;
        }
        break;
}

echo "域名 API 调用成功结束\n";

####### 基于腾讯云 DNS API 实现的 PHP 类，参考 https://cloud.tencent.com/document/product/302/4032

class TxyDns {

    private $accessKeyId = null;
    private $accessSecrec = null;
    private $DomainName = null;

    public function __construct($accessKeyId, $accessSecrec, $domain = "") {
        $this->accessKeyId = $accessKeyId;
        $this->accessSecrec = $accessSecrec;
        $this->DomainName = $domain;
    }

    /*
      根据域名返回主机名和二级域名
     */

    public static function getDomain($domain) {

        //常见根域名 【https://en.wikipedia.org/wiki/List_of_Internet_top-level_domains】
        // 【http://www.seobythesea.com/2006/01/googles-most-popular-and-least-popular-top-level-domains/】
	global $domainfile;
	$tmp = file($domainfile);
	$arr = array();
	foreach ($tmp as $k=>$v) {
		$v = trim($v);
		if ($v!="")
			$arr[]= "." . $v;
	}

        //二级域名
        $seconddomain = "";
        //子域名
        $selfdomain = "";
        //根域名
        $rootdomain = "";
        foreach ($arr as $k => $v) {
            $pos = stripos($domain, $v);
            if ($pos) {
                $rootdomain = substr($domain, $pos);
                $s = explode(".", substr($domain, 0, $pos));
                $seconddomain = $s[count($s) - 1] . $rootdomain;
                for ($i = 0; $i < count($s) - 1; $i++)
                    $selfdomain .= $s[$i] . ".";
		$selfdomain = substr($selfdomain,0,strlen($selfdomain)-1);
		break;
            }
        }
        //echo $seconddomain ;exit;
        if ($rootdomain == "") {
            $seconddomain = $domain;
            $selfdomain = "";
        }
        return array($selfdomain, $seconddomain);
    }

    public function error($code, $str) {
        echo "操作错误:" . $code . ":" . $str;
        exit;
    }

    public function RecordDelete($recordId) {
        $param["Domain"] = $this->DomainName;
        $param["RecordId"] = $recordId;

        $data = $this->send("DeleteRecord", "POST", $param);
        return ($this->out($data));
    }

    public function RecordList($subDomain, $recordType = "") {

        if ($recordType != "")
            $param["RecordType"] = $recordType;
        $param["Subdomain"] = $subDomain;
        $param["Domain"] = $this->DomainName;

        $data = $this->send("DescribeRecordList", "POST", $param);
        return ($this->out($data));
    }

    // public function RecordModify($subDomain, $recordType = "TXT", $value, $recordId) {
    //     $param["recordType"] = $recordType;
    //     $param["subDomain"] = $subDomain;
    //     $param["recordId"] = $recordId;
    //     $param["domain"] = $this->DomainName;
    //     $param["recordLine"] = "默认";
    //     $param["value"] = $value;

    //     $data = $this->send("RecordModify", "GET", $param);
    //     return ($this->out($data));
    // }

    public function RecordCreate($subDomain, $recordType = "TXT", $value) {
        $param["RecordType"] = $recordType;
        $param["SubDomain"] = $subDomain;
        $param["Domain"] = $this->DomainName;
        $param["RecordLine"] = "默认";
        $param["Value"] = $value;

        $data = $this->send("CreateRecord", "POST", $param);
        return ($this->out($data));
    }

    // public function DomainList() {

    //     $data = $this->send("DomainList", "POST", array());
    //     return ($this->out($data));
    // }

    function sign($key, $msg) {
        return hash_hmac("sha256", $msg, $key, true);
    }
    
    private function send($action, $reqMethod, $requestParams) {

        // 实例化一个认证对象，入参需要传入腾讯云账户 SecretId 和 SecretKey，此处还需注意密钥对的保密
        // 代码泄露可能会导致 SecretId 和 SecretKey 泄露，并威胁账号下所有资源的安全性。以下代码示例仅供参考，建议采用更安全的方式来使用密钥，请参见：https://cloud.tencent.com/document/product/1278/85305
        // 密钥可前往官网控制台 https://console.cloud.tencent.com/cam/capi 进行获取
        $secret_id = $this->accessKeyId;
        $secret_key = $this->accessSecrec;
        $token = "";

        $service = "dnspod";
        $host = "dnspod.tencentcloudapi.com";
        $req_region = "";
        $version = "2021-03-23";
        $payload = json_encode($requestParams);
        $endpoint = "https://dnspod.tencentcloudapi.com";
        $algorithm = "TC3-HMAC-SHA256";
        $timestamp = time();
        $date = gmdate("Y-m-d", $timestamp);

        // ************* 步骤 1：拼接规范请求串 *************
        $http_request_method = $reqMethod;
        $canonical_uri = "/";
        $canonical_querystring = "";
        $ct = "application/json; charset=utf-8";
        $canonical_headers = "content-type:".$ct."\nhost:".$host."\nx-tc-action:".strtolower($action)."\n";
        $signed_headers = "content-type;host;x-tc-action";
        $hashed_request_payload = hash("sha256", $payload);
        $canonical_request = "$http_request_method\n$canonical_uri\n$canonical_querystring\n$canonical_headers\n$signed_headers\n$hashed_request_payload";

        // ************* 步骤 2：拼接待签名字符串 *************
        $credential_scope = "$date/$service/tc3_request";
        $hashed_canonical_request = hash("sha256", $canonical_request);
        $string_to_sign = "$algorithm\n$timestamp\n$credential_scope\n$hashed_canonical_request";

        // ************* 步骤 3：计算签名 *************
        $secret_date = $this->sign("TC3".$secret_key, $date);
        $secret_service = $this->sign($secret_date, $service);
        $secret_signing = $this->sign($secret_service, "tc3_request");
        $signature = hash_hmac("sha256", $string_to_sign, $secret_signing);

        // ************* 步骤 4：拼接 Authorization *************
        $authorization = "$algorithm Credential=$secret_id/$credential_scope, SignedHeaders=$signed_headers, Signature=$signature";

        // ************* 步骤 5：构造并发起请求 *************
        $headers = [
            "Authorization" => $authorization,
            "Content-Type" => "application/json; charset=utf-8",
            "Host" => $host,
            "X-TC-Action" => $action,
            "X-TC-Timestamp" => $timestamp,
            "X-TC-Version" => $version
        ];
        if ($req_region) {
            $headers["X-TC-Region"] = $req_region;
        }
        if ($token) {
            $headers["X-TC-Token"] = $token;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    private function curl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function out($msg) {
        return json_decode($msg, true);
    }

}
