<?php
namespace App\Gridz;

use App\Helpers\CommonUtils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Charts\GridzChart;

trait GridzGraph
{
    /*
    https://v6.charts.erik.cat/adding_datasets.html#database-eloquent-example
    https://www.chartjs.org/docs/latest/general/fonts.html
    https://vis4.net/palettes/#/9|d|00429d,96ffea,ffffe0|ffffe0,ff005e,93003a|0|1
    */
    var $maxInParm=5;
    var $defaultSumType='sum';
    var $defaultGraphType='line';
    var $arrAcceptChartType=['line','bar','pie','horizontalBar'];
    var $arrChartDetail=[
        'horizontalBar'=>[
            'xAxisMaxCnt'=>2,
        ],
        'line'=>[
            'xAxisMaxCnt'=>2,
            'combineType'=>['bar']
        ],
        'bar'=>[
            'xAxisMaxCnt'=>2,
            'combineType'=>['line']
        ],
        'pie'=>[
            'xAxisMaxCnt'=>1,
            'option'=>[
                'scales'=>[
                    'yAxes'=>false
                ],
                
                'tooltips'=>[
                    'callbacks'=>[
                        'labelJsFn'=>'labelJsFn_piejsfn',
                    ]
                ],
                'legend'=>[
                    'labels'=>[
                        'generateLabelsJsFn'=>'generateLabels_piejsfn',
                    ]
                ]  
            ],//option
            'plugin'=>[
               
            ],
        ]
    ];
    var $jsFnDet=[
        "['tooltips']['callbacks']['label']"=>[
            'in'=>'tooltipItem, data'
        ],
        "['tooltips']['callbacks']['footer']"=>[
            'in'=>'tooltipItem, data'
        ],
        "['legend']['labels']['generateLabels']"=>[
            'in'=>'chart'
        ],
        "['legend']['onClick']"=>[
            'in'=>'mouseEvent, legendItem'
        ],
        "['onClick']"=>[
            'in'=>'mouseEvent, legendItem'
        ]
    ];
    var $defaultGraphOption=[
        'responsive'=>true,
    ];
    var $defaultGraphPlugin=[
       
    ];
    var $arrChartColor=[
        '#00429d',
        '#4771b2',
        '#73a2c6',
        '#a5d5d8',
        '#ffffe0',
        '#ffbcaf',
        '#f4777f',
        '#cf3759',
        '#93003a'
    ];
    var $arrChartColor2 = [
        '#4dc9f6',
        '#f67019',
        '#f53794',
        '#537bc4',
        '#acc236',
        '#166a8f',
        '#00a950',
        '#58595b',
        '#8549ba'
      ];
      
    var $chartDefaultColor='#ffffff00';
    public function loadGraph($param, &$msg=false){
        if(($this->genGraphColumns($param, $msg))===false){
            return '';
        }
        
        $graphParam=$param['graph'];

        $chart = new GridzChart;

        $_key=$param['_key'];
        $_method=$param['_method'];
        $_interface=$param['_class'];
        $param['graphActionUrl']=$param['action_ajax'].'?i='.$_interface.'&m='.$_method.'&a=getGraph&k='.$_key;
        $chart->load($param['action_ajax']);
        $option=!empty($graphParam['option'])?$graphParam['option']:[];
        $option=array_merge_recursive(!empty($this->defaultGraphOption)?$this->defaultGraphOption:[],$option);
        if(!empty($graphParam['title'])){
            $option['title']=[
                'display'=>true,
                'text'=>$graphParam['title'],
            ];
        }
        if(empty($option['legend']['position'])){
            $option['legend']['position']='bottom';
        }
        
        if(isset($option['plugins'])){
            $option['plugins']=json_encode($option['plugins']);
        }
        $graphJs=[];
        $this->renderGraphJs($option, $graphJs);
       // dd($graphJs, $option);
        $chart->options($option);

        $plugin=!empty($graphParam['plugin'])?$graphParam['plugin']:[];
        $plugin=array_merge_recursive(!empty($this->defaultGraphPlugin)?$this->defaultGraphPlugin:[],$plugin);
        $chart->plugins($plugin);
       
        if(!empty($graphParam['labels'])){
            $labels=array_values($graphParam['labels']);
            $chart->labels($labels);
        }
        $param['_chart']=$chart;
        $param['graphJs']=$graphJs;
        $param['maxInParm']=$this->maxInParm;
                       
       
        return $this->CI->parser->parse($param['template']['graph'], $param, TRUE);
    }
  
    private function renderGraphJs(&$option, &$graphJs=[],$option2=false, $arr=[]){
        $postJsFn='JsFn';
        if($option2===false){
            $option2=$option;
        }
        foreach($option2 as $code => $value){
            if(!is_array($value)){
                if(substr($code,-1*strlen($postJsFn))!=$postJsFn)continue;
                $fn=substr($code,0,-1*strlen($postJsFn));
                $arr[]=$fn;
                $assocStr="['".implode("']['",$arr)."']";
                $param=['jsfn'=>$value,'fn'=>$fn,'assoc'=>$arr,'assocStr'=>"['".implode("']['",$arr)."']"];
                if(!empty($this->jsFnDet[$assocStr])){
                    $param['param']=$this->jsFnDet[$assocStr];
                }
                $param['maxInParm']=!empty($option['maxInParm'])?$option['maxInParm']:$this->maxInParm;
                $graphJs[]=$param;
                eval('$option'.$assocStr.'=false;');
            }else{
                //$arr1[]=$code;
                $arr1=$arr;
                $arr1[]=$code;
                $this->renderGraphJs($option, $graphJs,$value, $arr1);
            }
        }
        //var_dump($graphJs);
    }
    var $nodata=0;
    public function getGraphData($param, $request, $columns, $values, $dataTableColumns){
        if(($this->genGraphColumns($param))===false){
            return '';
        }       
        $graphParam=$param['graph'];

        $dataset=$graphParam['dataset'];
        $_columns=$param['columns'];
        $xAxis=$graphParam['columns'];
        $arrC=$arrG=[];
        foreach($xAxis as $col){
            $colInfo=$dataTableColumns[$col];
            $arrC[]='gridztable.'.$colInfo['alias'].' '.$col;
            $arrG[]='gridztable.'.$colInfo['alias'];
        }
        foreach($dataset as $ii=> $graphRow){
            if(empty($graphRow['_canRender']))continue;
            $col=!empty($graphRow['column'])?$graphRow['column']:false;
            $colInfo=$dataTableColumns[$col];
            if(!empty($graphRow['select'])){
                $selectCol=$graphRow['select'];
            }else{
                $sumType=!empty($graphRow['sumType'])?$graphRow['sumType']:$this->defaultSumType;
                $selectCol=$sumType.'(gridztable.'.$colInfo['alias'].')';
            }
            $arrC[]=$selectCol.' '.$col;
            if(!empty($graphRow['data'])){
                $idx=0;
                foreach($graphRow['data'] as $data_code => $data_colInfo){
                    if(empty($data_colInfo['select'])){
                        $data_colInfo['select']=0;
                    }
                    $alias=$col.'_'.$idx;
                    $arrC[]=$data_colInfo['select'].' '.$alias;
                    $dataset[$ii]['data'][$data_code]['_idx']=$idx;
                    $dataset[$ii]['data'][$data_code]['_alias']=$alias;
                    $idx++;
                }
            }
        }
            
        $_table="(".str_replace('SQL_CALC_FOUND_ROWS','',$this->data_tables->last_query).") gridztable ";
        $data=[];
        if(empty($this->nodata)){
            $data= $this->data_tables->summary($request, $_table, [], implode(', ',$arrC) , $arrG, $columns,[],$values);
        }
        $compileData=$arrLabel=[];
        $labelLoaded=false;
        $chart = new GridzChart;
        if(!empty($graphParam['labels'])){
            $arrLabel=$graphParam['labels'];
            $labelLoaded=true;
        }
        foreach($data as $row){
            $_c='';
            foreach($xAxis as $idx=> $c){
                if(!$idx && !$labelLoaded){
                    $arrLabel[$row->$c]=$row->$c;
                }
                $_c='[$row->'.$c.']'.$_c;
            }
            $param['thousands_sep']='';
            foreach($dataset as $idx=> $graphRow){
                if(empty($graphRow['_canRender']))continue;
                $compileData=[];
                $col=!empty($graphRow['column'])?$graphRow['column']:false;
                $d=isset($row->$col)?$row->$col:0;
                $d=$this->convertNumberFormat($d,$graphRow['colInfo'],$param);
                eval('$dataset[$idx][\'_data\']'.$_c.'=$d;');
                if(!empty($graphRow['data'])){
                    eval('$dataset[$idx][\'_dataRow\']'.$_c.'=$row;');
                }
            }
        }

        $curColorIdx=0;$lvlcnt=sizeof($xAxis);
        $arrNewLabels=[];
        foreach($dataset as $graphRow){
            if(empty($graphRow['_data'])){
                $graphRow['_data']=[];
            };
            $col=!empty($graphRow['column'])?$graphRow['column']:false;
            $type=!empty($graphRow['type'])?$graphRow['type']:$this->defaultGraphType;
            $this->_renderGraphDataset($chart,$type,$graphRow['_data'],$graphRow,$arrLabel,$xAxis,$dataset,$lvlcnt);
           
            if(!empty($graphRow['_label'])){
                $arrNewLabels=array_merge_recursive($arrNewLabels,$graphRow['_label']);
            }
        }   

        if(!empty($arrNewLabels)){
            $arrLabel=$arrNewLabels;
        }else{
            $arrLabel=array_values($arrLabel);
        }

        $result= [
            'label'=>$arrLabel,
            'data'=>json_decode($chart->api(),1)
        ];
        
        $result['sql']=$this->data_tables->last_query;
        if(!empty($values)){
            $result['values']=$values;
        }
        return $result;
    }
    
    protected function _renderGraphDataset(&$chart,$type,$d,&$graphRow,$arrLabel,$xAxis,$dataset,$lvl){
        $title=!empty($graphRow['title'])?$graphRow['title']:'';   
        $datasetCnt=sizeof($dataset);
        $stack=!empty($graphRow['option']['stack'])?true:false;
        if($lvl>=2){
            $_title=!empty($graphRow['_title'])?$graphRow['_title']:'';   
            foreach($d as $grp=> $d2){
                $graphRow['_title']=($datasetCnt>1 && $_title?$_title.' - ':'').$grp;
                $this->_renderGraphDataset($chart,$type,$d2,$graphRow,$arrLabel,$xAxis,$dataset,$lvl-1);
            }
            return;
        }
        $ds=[];$total=$cnt=0;
        foreach($arrLabel as $code => $label){
            $n=!empty( $d[$code])?$d[$code]:0;
            $ds[]=$n;
            //$total+=$n;
            $cnt++;
        } 
        $options=$rowOptions=[];
        if(!empty($graphRow['option'])){
            $rowOptions=$graphRow['option'];
        }
        if(!empty($rowOptions['borderColor'])){
            $borderColor=$rowOptions['borderColor'];
            unset($rowOptions['borderColor']);
            if(!empty($rowOptions['backgroundColor'])){
                $backgroundColor=$rowOptions['backgroundColor'];
                unset($rowOptions['backgroundColor']);
            }else{
                $backgroundColor= $this->adjustBrightness($borderColor);
            }
        }else{
            $colorDet= $this->getNextColor();
            $borderColor=!empty($colorDet[0])?$colorDet[0]:$this->chartDefaultColor;
            $backgroundColor=!empty($colorDet[1])?$colorDet[1]:'';
        }
        $dataByAxis=true;$invertAxis=false;
        switch($type){
            case 'horizontalBar':
                $invertAxis=true;
                if(!empty($graphRow['sort'])){
                    $desc=$graphRow['sort']==='desc'?true:false;
                    $arrLabel2=array_keys($arrLabel);
                    $ds2=$ds;
                    uasort($ds,function ($a, $b) use($desc) {
                        if ($a == $b) {
                            return 0;
                        }
                        if($desc)return ($a > $b) ? -1 : 1;
                        return ($a < $b) ? -1 : 1;
                    });
                    $newLabel=[];
                    foreach($ds as $ii => $v){
                        $labelKey=$arrLabel2[$ii];
                        $newLabel[]=$arrLabel[$labelKey];
                    }
                    $graphRow['_label']=$newLabel;
                    $ds = array_values($ds);
                }
                break;
            case 'pie':
                $dataByAxis=false;
                $color2=$this->getNextColor2();
                $backgroundColor=$borderColor=[];
                $i=0;
                foreach($arrLabel as $label){
                    if($datasetCnt>1){
                        $perc=($i+1)/sizeof($arrLabel)*0.3;
                        $color_new=$this->adjustBrightness($color2[0], $perc);
                        $backgroundColor_new=$this->adjustBrightness($color_new);
                    }elseif($i==0){
                        $color_new=$color2[0];
                        $backgroundColor_new=$color2[1];
                    }else{
                        $colors=$this->getNextColor2();
                        $color_new=$colors[0];
                        $backgroundColor_new=$colors[1];
                    }
                    $borderColor[]=$color_new;
                    $backgroundColor[]=$backgroundColor_new;
                    $graphRow['_label'][]=$label;
                    $i++;
                }
            break;
            case 'line':
                if(!isset($rowOptions['fill'])){
                    $options['fill']=false;
                }
                $backgroundColor=$borderColor;
            break;
            default:
                
            break;
        }
        
        $options['borderColor']=$borderColor;
        $options['backgroundColor']=$backgroundColor;
        $options=array_merge_recursive($options, $rowOptions);
        $arrData=$ds;
        if($dataByAxis){
            $arrData=[];
            $x='x';
            $y='y';
            if($invertAxis){
                $x='y';
                $y='x';
            }
            $labels=!empty($graphRow['_label'])?$graphRow['_label']:array_values($arrLabel);
            foreach($ds as $i=> $dd){
                $labelKey=array_search($labels[$i], $arrLabel);
                $d=[
                    $x=>$labels[$i],
                    $y=>$dd,
                ];
                if(!empty($graphRow['data']) && !empty($graphRow['_dataRow'][$labelKey])){
                    $_dataRow=$graphRow['_dataRow'][$labelKey];
                    foreach($graphRow['data'] as $data_code => $data_colInfo){
                        if(empty($data_colInfo['_alias']))continue;
                        $alias=$data_colInfo['_alias'];
                        if(isset($_dataRow->$alias)){
                            $d[$data_code]=$_dataRow->$alias;
                        }
                    }
                }
                $arrData[]=$d;
            }
        }
        $chart->dataset($title, $type, $arrData)
            ->options($options);        
    }
    var $curColorIdx=0;
    protected function getNextColor(){
        $color= $this->arrChartColor[$this->curColorIdx];
        $this->curColorIdx++;
        if(!isset($this->arrChartColor[$this->curColorIdx])){
            $this->curColorIdx=0;    
        }
        return [$color,$this->adjustBrightness($color)];
    }
    var $curColor2Idx=0;
    protected function getNextColor2(){
        $color2= $this->arrChartColor2[$this->curColor2Idx];
        $this->curColor2Idx++;
        if(!isset($this->arrChartColor2[$this->curColor2Idx])){
            $this->curColor2Idx=0;    
        }
        return [$color2,$this->adjustBrightness($color2)];
    }
  
    protected function adjustBrightness($hexCode, $adjustPercent=0.6) {
        $hexCode = ltrim($hexCode, '#');
    
        if (strlen($hexCode) == 3) {
            $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
        }
    
        $hexCode = array_map('hexdec', str_split($hexCode, 2));
    
        foreach ($hexCode as & $color) {
            $adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
            $adjustAmount = ceil($adjustableLimit * $adjustPercent);
    
            $color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
        }
    
        return '#' . implode($hexCode);
    }
    public function renderGraphRequest(&$request,$param){
        if(!empty($request['nodata'])){
            $this->nodata=true;
        }
    }
    public function genGraphColumns(&$param, &$msg=false){
        if(empty($param['graph']))return false;
        $graphParam=$param['graph'];
        if(empty($graphParam['columns']) || empty($graphParam['dataset'])){
            $msg='{graph.columns} or {graph.dataset} not configured';
            return false;
        }
        $dataset=$graphParam['dataset'];
        $columns=$param['columns'];
        $xAxis=$graphParam['columns'];
       
        $canRender=false;
        foreach($xAxis as $col){
            if(empty($columns[$col])){
                $msg=$col.' not in {columns}';
                return false;
            }
        }
        $arrType=[];$firstType=false;
        foreach($dataset as $idx=> $graphRow){
            $col=!empty($graphRow['column'])?$graphRow['column']:false;
            if($col===false || empty($columns[$col])){
                continue;
            }
            $colInfo=$columns[$col];
            $isNumeric=in_array($colInfo['datatype'],['number']);
            if(!$isNumeric){
                continue;
            }
            
            $title=!empty($graphRow['title'])?$graphRow['title']:$col;
            $type=!empty($graphRow['type'])?$graphRow['type']:$this->defaultGraphType;
            $acceptType=in_array($type,$this->arrAcceptChartType);
            if(!$acceptType){
                continue;
            }
            $arrType[$type]=$type;
            if(isset($this->arrChartDetail[$type])){
                $typeDet=$this->arrChartDetail[$type];
                if(!empty($typeDet['xAxisMaxCnt'])){
                    if(sizeof($xAxis)>$typeDet['xAxisMaxCnt']){
                        $msg='too much {graph.columns} - '.$type.'-'.$typeDet['xAxisMaxCnt'];
                        return false;//not handled yet
                    }
                }
                if($firstType===false){
                    if(!empty($typeDet['option'])){
                        $option=!empty($param['graph']['option'])?$param['graph']['option']:[];
                        $param['graph']['option']=array_merge_recursive($option, $typeDet['option']);
                    }
                    if(!empty($typeDet['plugin'])){
                        $plugin=!empty($param['graph']['plugin'])?$param['graph']['plugin']:[];
                        $param['graph']['plugin']=array_merge_recursive($plugin, $typeDet['plugin']);
                    }
                    $firstType=true;
                }
            }
            if(!empty($graphRow['title']) && in_array($type,['bar','horizontalBar','line'])){
                $param['graph']['option']['scales']['yAxes'][$idx]['scaleLabel']['display']=true;
                $param['graph']['option']['scales']['yAxes'][$idx]['scaleLabel']['labelString']=$graphRow['title'];
                if($idx>0){
                    $param['graph']['option']['scales']['yAxes'][$idx]['position']='right';

                }
            }
            $param['graph']['dataset'][$idx]['colInfo']=$colInfo;
            $param['graph']['dataset'][$idx]['_canRender']=true;
            $canRender=true;
        }
        
        if(!$canRender){
            $msg='no valid {graph.dataset}';
            return false;
        }  
        if(!empty($arrType) && sizeof($arrType)>1){
            foreach($arrType as $type){
                if(isset($this->arrChartDetail[$type])){
                    $typeDet=$this->arrChartDetail[$type];
                    $combineType=!empty($typeDet['combineType'])?$typeDet['combineType']:[];
                    $arrType2=$arrType;
                    unset($arrType2[$type]);
                    foreach($arrType2 as $type2){
                        if(!in_array($type2,$combineType)){
                            $msg='invalid {graph.dataset.combineType} '.$type.'-'.$type2;
                            return false;
                        }
                    }
                }
            }
        }
       
        return true; 
    }
}
