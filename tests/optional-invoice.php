<?php
// Run the actual sale dispatch with isolated document/stock dependencies.
$store=[];$sales=0;$documents=0;$stock=0;$missingAddress=true;
function pz_text($d,$k,...$args){return $d[$k];}
function pz_store($kind,$key){global $store;return $store[$kind][$key]??null;}
function pz_put($kind,$key,$v){global $store;$store[$kind][$key]=$v;}
function pz_visit($id){}
function pz_sale($data){global $sales;return ['id'=>++$sales,'total'=>19000];}
function pz_stock_apply(...$args){global $stock;$stock++;}
function pz_document_from_visit($data){global $documents,$missingAddress;if($missingAddress)throw new InvalidArgumentException('Missing address');$id='invoice-'.++$documents;pz_put('document',$id,['number'=>'FV/'.$documents]);return ['id'=>$id];}
$api=file_get_contents(dirname(__DIR__).'/custom/puchatyzakatek/api.php');
$start=strpos($api,"    case 'sale':");$end=strpos($api,"    case 'plan':",$start);
$dispatch=substr($api,$start,$end-$start);
$run=function($data)use($dispatch){eval('switch("sale"){'.$dispatch.'}');return $result;};
function check($v,$m){if(!$v)throw new RuntimeException($m);}
$base=['requestKey'=>'fixture-sale-000001','items'=>[['id'=>'service']]];
$r=$run($base);check(!isset($r['documentId'])&&$documents===0,'Default must not issue invoice or require address');
$again=$run($base+['issueInvoice'=>true]);check($again===$r&&$sales===1&&$stock===1&&$documents===0,'Retry changed invoice choice or duplicated sale');
$missingAddress=false;$with=$base;$with['requestKey']='fixture-sale-000002';$with['issueInvoice']=true;
$r=$run($with);check(isset($r['documentNumber'])&&$documents===1,'Explicit invoice not created');
$with['issueInvoice']=false;check($run($with)===$r&&$documents===1&&$sales===2,'Invoice retry changed original result');
$missingAddress=true;$with['requestKey']='fixture-sale-000003';$with['issueInvoice']=true;
try{$run($with);throw new RuntimeException('Invoice accepted missing address');}catch(InvalidArgumentException $e){}
echo "PASS: optional invoice, address check only for invoice, immutable retry result, no duplicate stock or documents.\n";
