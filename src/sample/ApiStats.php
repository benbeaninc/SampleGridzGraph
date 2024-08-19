<?php 
public static function getApiStatsParam($isExport=false){
        return [
            //'title'=>'Api Stats',
            'type'=>'graph',
            'containerClass'=>'dashboard',

            'param'=>[    
                'method'=>__METHOD__,
                'path'=>__FILE__,
                'controller' => 'Home',
                'hideSearchLabel'=>true,
                'hasGraph'=>true,
                'canSearch'=>true,
                'hideFooterBtn'=>true,
                'canResetSearch'=>false,
                'columns'=>[
                    'rpt_type'=>[
                        '_blank'=>true,
                        'lookup'=>[
                            'H'=>'Hourly',
                            'W'=>'Weekly',
                            'M'=>'Monthly'
                        ],
                        'searchFieldModifierFn'=>function(&$content, $fparam, $p){
                            $content=str_replace(['<select'],['<select style="display:none"'], $content);
                            $arrHtml=[];
                            $required=$fparam['required'];
                            $selected=false;
                            foreach($fparam['arrOption'] as $code => $value){
                                if($selected===false && empty($fparam['value'])){
                                    $fparam['value']=$code;
                                    $selected=true;
                                }
                                $select=$code==$fparam['value']?'checked':'';
                                $active=$code==$fparam['value']?'active':'';
                                $arrHtml[]="<label class='btn btn-primary btn-rounded ".$active." btn-xs form-check-label'><input class='form-check-input' ".$select." id='".$fparam['fieldId']."_".$code."' name='".$fparam['fieldname']."' value='".$code."' type='radio'>".$value."</label>";
                            }
                            $content="<div class='btn-group ".$fparam['fieldname']."_radio' data-toggle='buttons'>".implode('',$arrHtml)."</div>";
                        }
                    ],
                    'asr_api_count'=>[
                        'title'=>'asr_api_count',
                        'datatype'=>'number'
                    ],
                    'asr_api_max_runtime'=>[
                        'title'=>'asr_api_max_runtime',
                        'datatype'=>'number',
                        'decimal'=>2
                    ],
                    'asr_api_total_runtime'=>[
                        'title'=>'asr_api_total_runtime',
                        'datatype'=>'number',
                        'decimal'=>2
                    ],
                    'asr_api_code'=>[
                        'title'=>'asr_api_code',
                    ],
                    'asr_domain'=>[
                        'title'=>'asr_domain',
                    ],
                    'asr_date'=>[
                        'title'=>'asr_date',
                        'selectSearch'=>'asr_date',
                        'input'=>'date',
                        'dateOpt'=>[
                            'hideTime'=>true
                        ]
                    ],
                ],
                'searchGrid'=>[
                    [
                        'rpt_type'=>['col'=>6],
                    ]
                ],
               
                'graph'=>[
                    'title'=>'API stats Run Time',
                    'height'=>'400px',
                    'columns'=>['asr_date'],
                    'option'=>[
                        'legend'=>[
                            'position'=>'bottom'
                        ],
                      
                    ],
                    'dataset'=>[
                        [
                            'title'=>'max run time',
                            'column'=>'asr_api_max_runtime',
                            'type'=>'line',
                            'sumType'=>'max'
                        ],
                        [
                            'title'=>'avg run time',
                            'column'=>'asr_api_total_runtime',
                            'type'=>'line',
                            'select'=>'sum(asr_api_total_runtime)/ sum(asr_api_count)',
                            'option'=>[
                                'borderDash'=>[5,5],
                                'borderColor'=>'#4bc0c0',
                            ]
                        ],
                    ],
                ],
                'graphPostJsFn'=>'apiStats_graphPostJsFn',
                'model'=>[
                    'db'=>self::getDashboardDatabase(),
                    'table'=>'rpt_api_statistics',
                    'order'=>[
                        'asr_date'=>['desc'=>true],
                    ]
                ],
                'genDataTableColumn_modifierfn'=>function(&$p, $request, $is_export){
                    if(empty($request['extra_search']))return;
                    foreach($request['extra_search'] as $extraSearch){
                       
                        switch($extraSearch['name']){
                            case 'rpt_type':
                                $select='asr_date';
                                $labels=[];
                                switch($extraSearch['value']){
                                    case 'H':
                                        $select='asr_hour';
                                        $labels=CommonUtils::getAllHours();
                                        break;
                                    case 'W':
                                        $select='date_format(asr_date,\'%w\')';
                                        $labels=CommonUtils::getAllDays();

                                        break;
                                    case 'M':
                                        $select='date_format(asr_date,\'%m\')';
                                        $labels=CommonUtils::getAllMonths();

                                        break;
                                }
                                $p['columns']['asr_date']['select']=$select;
                                $p['graph']['labels']=$labels;
                            break;
                        }//end switch name
                    }//end foreach
                }
            ]
        ];
    }
