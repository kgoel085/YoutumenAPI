<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Mockery\Exception;
use phpDocumentor\Reflection\Types\Boolean;

class EndPointController extends Controller
{
    private $requestObj;
    private $configObj;
    private $currentAction = null;
    private $currentEndpoint = null;
    private $youtubeKey = null;
    private $paramValidateArr = array();

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->middleware('jwt.auth');

        if($request) $this->requestObj = $request;
        if(env('YOUTUBE_KEY')) $this->youtubeKey = env('YOUTUBE_KEY');

        //Set configuration
        $this->setConfiguration();
    }

    // Set all the required parameters for the current request
    public function setConfiguration(){
        $configFile = str_replace('\\', '/', base_path()).'/config/endpoints.json';

        $jsonContent = "";
        $jsonContent = file_get_contents($configFile);

        if(empty($jsonContent) == false){
            $tmpContentArr = json_decode($jsonContent, true);

            //Set global configured variables
            if(count($tmpContentArr['Global']) > 0) $this->configObj['Global'] = $tmpContentArr['Global'];

            //Set endpoint related variables
            if(count($tmpContentArr['Endpoints'] > 0)){

                //All availabele endpoints
                $this->configObj['AllowedEndpoints'] = array_keys($tmpContentArr['Endpoints']);
                $this->configObj['EndpointConfig'] = $tmpContentArr['Endpoints'];
                $this->configObj['CommonFilters'] = $tmpContentArr['CommonParams'];
            }
        }
    }

    // Set if current action is valid or not
    public function checkAction(&$action){
        $returnVal = false;
        if($action == 'home') $action = 'trending';

        if($action || in_array($action, $this->configObj['AllowedEndpoints'])){
            $this->currentAction = trim($action);
            $this->currentEndpoint = $this->configObj['EndpointConfig'][$this->currentAction]['endpoint'];
            $returnVal = true;
        }

        if(!$this->currentEndpoint) $returnVal = false;

        return $returnVal;
    }

    // Set validation base for the current request, and check if any unwanted parameter is set 
    public function checkParameters(){
        $returnArr = array('valid' => true, 'msg' => null);
        $requestedVars = $allowedVars = array();

        //Requested parameters
        $requestedVars = array_keys($this->requestObj->all());

        //Master filter array
        $commonFilters = $this->configObj['CommonFilters'];
        
        //Action specific filter array
        $currentParams = $this->configObj['EndpointConfig'][$this->currentAction]['params'];

        if(is_array($currentParams) && count($currentParams) > 0){
            foreach($currentParams as $paramType => $paramFilters){

                //Current type of param : required, filters, optional
                $currentFilter = &$commonFilters[$paramType];

                if($currentFilter){
                    if(is_array($paramFilters) && count($paramFilters) > 0){
                        $tmpArr = array();
                        foreach($paramFilters as $paramKey => $paramVal){

                            //User has proived a param to overwrite
                            if(is_array($paramVal)){
                                $getKeys = array_keys($paramVal);
                                if(count($getKeys) > 0 && is_array($getKeys)){

                                    //Overwrite the master filter array with action specific values
                                    foreach($getKeys as $findKey){
                                        if($currentFilter[$paramKey][$findKey]){

                                            $currentFilter[$paramKey][$findKey] = $paramVal[$findKey];

                                            //Add it to allowed variable
                                            if(!in_array($paramKey, $tmpArr)) $tmpArr[] = $paramKey;
                                        }
                                    }
                                }
                            }else{
                                //Action has requested specific filters to be validated not all filters in master
                                if(!in_array($paramVal, $tmpArr)) $tmpArr[] = $paramVal;
                            }
                        }

                        if(count($tmpArr) > 0){
                            //Remove the filters that are not required by the action to be validated
                            foreach($currentFilter as $currentKey => $currentVal){
                                if(!in_array($currentKey, $tmpArr)){
                                    unset($currentFilter[$currentKey]);
                                }else{
                                    //Store all the allowed params in one array
                                    if(!in_array($currentKey, $allowedVars)) $allowedVars[] = $currentKey; 
                                }
                            }
                        }
                    }
                }
            }
        }

        //Check if any unallowed parameters is sent in request
        if(count($allowedVars) > 0){
            $diffParams = array_diff($requestedVars, $allowedVars);
            if($diffParams){
                $returnArr['valid'] = false;
                $returnArr['msg'] = 'Following parameters are not allowed. Invalid parameters: '.implode(',', array_unique($diffParams));
            }
        }

        //Check if required parameters are avaialbel or not
        if($commonFilters['required'] && $returnArr['valid']){
            $requiredExists = true;

            $requiredVars = array_keys($commonFilters['required']);
            if(is_array($requiredVars) && count($requiredVars) > 0){
                foreach($requiredVars as $reqVar){
                   if(!in_array($reqVar, $requestedVars))  $requiredExists = false;

                   if(!$requiredExists){
                        $possibleVals = ($commonFilters['required'][$reqVar]['possibleVals']) ? implode(',', $commonFilters['required'][$reqVar]['possibleVals']) : null;

                        $returnArr['valid'] = false;
                        $returnArr['msg'] = $reqVar.' is required.';
                        if($possibleVals) $returnArr['msg'] = $returnArr['msg'].' Possible values are: '.$possibleVals;

                        break;
                   }
                }
            }
        }

        //If request is valid, set the global class validate arr to be used in validateParameters()
        if($returnArr['valid']) $this->paramValidateArr = $commonFilters;

        return $returnArr;
    }

    //Perform required valdiations before specific action is performed
    public function validateParameters(){
        $returnArr = array('valid' => true, 'msg' => null, 'params' => null);
        $msgString = "";

        $validateArr = $this->paramValidateArr;
        $reqVars = $this->requestObj->all();

        $workflowArr = $validateArr['workflow'];
        if($workflowArr){
            try {
                //Perform validation according to the workflow
                foreach($workflowArr as $paramType => $paramAction){
                    $currentFilter = $validateArr[$paramType];
                    if($paramAction) $paramAction = array_values(array_filter($paramAction));

                    if($paramType == 'filters'){
                        //Check only one parameter is set out of a given parameters
                        $allowedParams = array_keys($currentFilter);
                        $requestedParams = array_keys($reqVars);

                        $tmpArr = array();
                        foreach($requestedParams as $reqParam){
                            if(count($tmpArr) > 1) break;
                            if(in_array($reqParam, $allowedParams)){
                                $tmpArr[] = $reqParam;
                            }
                        }

                        if(count($tmpArr) > 1){
                            throw new Exception("One or more filter is set. Please set only one filter from these: ".implode(',', $allowedParams));
                        }
                    }
    
                    // Action specific validations
                    $validParam = true;
                    foreach($currentFilter as $filterKey => $filterVals){
    
                        //Loop thorugh them according to the workflow
                        foreach($paramAction as $action){
                            $currentVal = (array_key_exists($filterKey, $reqVars)) ? $reqVars[$filterKey] : false;
                            if(!array_key_exists($action, $currentFilter[$filterKey]) || !$currentVal) continue;

                            switch($action){

                                //Check variable value type
                                case 'type':
                                    $currentActionVal = $currentFilter[$filterKey][$action];
                                    $currentValType = gettype($currentVal);
                                    switch($currentActionVal){
                                        case 'string':
                                            if($currentValType !== $currentActionVal){
                                                throw new Exception($filterKey.' should be a '.$currentActionVal.'. '.$currentValType.' provided');
                                            }
                                        break;

                                        case 'integer':
                                            $currentValType = (int) $currentVal;
                                            $range = $currentFilter[$filterKey]['range'];

                                            if(empty($currentValType) == false && $currentValType >= $range['min'] && $currentValType <= $range['max']){}
                                            else{
                                                throw new Exception($filterKey.' should an integer between '.$range['min'].' & '.$range['max']);
                                            }
                                        break;

                                        case 'boolean':
                                            $currentValType = (Boolean) $currentVal;
                                            if($currentValType === $currentActionVal){
                                                throw new Exception($filterKey.' should be a '.$currentActionVal.'. '.$currentValType.' provided');
                                            }
                                        break;

                                        case 'datetime':
                                            if(!$this->validateDate($currentVal)){
                                                throw new Exception($filterKey.' have invalid format/value');
                                            }
                                        break;
                                    }
                                break;

                                //Check if only possible values are provided
                                case 'possibleVals':
                                    $checkValuesArr = $currentFilter[$filterKey][$action];
                                    if(!$checkValuesArr) continue;

                                    if(stristr($currentVal, ',')) $currentVal = array_values(array_filter(explode(',', $currentVal)));

                                    if(is_array($currentVal)){
                                        foreach($currentVal as $currentKey){
                                            $searchedKey11 = "";
                                            $searchedKey11 = array_search($currentKey, $checkValuesArr);
                                            if($searchedKey11 === false || !$checkValuesArr[$searchedKey11]){
                                                throw new Exception($currentKey.' is an invalid value for '.$filterKey.' parameter. Possible valid values are: '.implode(',', $checkValuesArr));
                                                break;
                                            }
                                        }
                                    }else{
                                        $searchedKey = array_search(trim($currentVal), $checkValuesArr);

                                        if(!$checkValuesArr[$searchedKey]){
                                            throw new Exception($currentVal.' is an invalid value for '.$filterKey.' parameter. Possible valid values are: '.implode(',', $checkValuesArr));
                                        }
                                    }
                                break;

                                //Check if only allowed parameters are set
                                case 'allowed':
                                    $checkValuesArr = $currentFilter[$filterKey][$action];
                                    $reqSearch = array_keys($reqVars);

                                    //Unset the requested filter var
                                    if(array_search($filterKey, $reqSearch)) unset($reqSearch[array_search($filterKey, $reqSearch)]);

                                    $diffParams = array_diff($reqSearch, $checkValuesArr);
                                    if($diffParams){
                                        throw new Exception(implode(',', $diffParams).' are not acceptable with '.$filterKey);
                                    }
                                break;

                                //Check if all the required variables are set
                                case 'required':
                                    $checkValuesArr = $currentFilter[$filterKey][$action];

                                    $tmpArr = array();
                                    foreach($checkValuesArr as $checkKey => $checkVal){
                                        //If value is null, that means key only needs to be set in request
                                        if($checkVal === null){
                                            if(array_key_exists($checkKey, $reqVars)){

                                            }else{
                                                $tmpArr[] = $checkKey.' is required';
                                            }
                                        }else{
                                            //If value is there than key and value should match
                                            if($reqVars[$checkKey] && $reqVars[$checkKey] == $checkVal){

                                            }else{
                                                $tmpArr[] = $checkKey.' should be set to '.$checkVal;
                                            }
                                        }
                                    }

                                    if(count($tmpArr) > 0){
                                        throw new Exception($filterKey.' required parameters are missing. '.implode(',', $tmpArr));
                                    }
                                break;

                                //Check if google auth is required or not
                                case 'requiredAuth':
                                    $checkValuesArr = $currentFilter[$filterKey][$action];
                                    if($checkValuesArr){
                                        if(!$this->requestObj->gToken){
                                            throw new Exception($filterKey.' requires authorization tokken.');
                                        }
                                    }
                                break;

                                //Check if not required params are set or not
                                case 'notRequired':
                                    $reqSearch = "";
                                    $checkValuesArr = $currentFilter[$filterKey][$action];
                                    $reqSearch = array_keys($reqVars);

                                    //Unset the requested filter var
                                    if(array_search($filterKey, $reqSearch)) unset($reqSearch[array_search($filterKey, $reqSearch)]);

                                    $tmpArr = array();

                                    foreach($checkValuesArr as $checkKey){
                                        if(array_search($checkKey, $reqSearch)){
                                            $tmpArr[] = $checkKey;
                                        }
                                    }

                                    if(count($tmpArr) > 0){
                                        throw new Exception(implode(',', $tmpArr).' are not required with '.$filterKey);
                                    }
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $th) {
                $returnArr['valid'] = false;
                $returnArr['msg'] = $th->getMessage();
            }
        }

        if($returnArr['valid']) $returnArr['params'] = $reqVars;

        return $returnArr;
    }

    //Validates the date in correct format or not
    function validateDate($date){
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $date, $parts) == true) {
            $time = gmmktime($parts[4], $parts[5], $parts[6], $parts[2], $parts[3], $parts[1]);

            $input_time = strtotime($date);
            if ($input_time === false) return false;

            return $input_time == $time;
        } else {
            return false;
        }
    }

    /**
     * All the actions will be validated and then will be executed
     */
    public function performAction($action = null){
        //If key is not set API can't be used
        if(!$this->youtubeKey){
            return response()->json([
                'error' => 'Key cannot be empty'
            ], 503);
        }

        //Check if current action is allowed or not
        if(!$this->checkAction($action)){
            return response()->json([
                'error' => 'Invalid action provided.'
            ], 400);
        }

        //Check if request parameters are also allowed or not
        $validParams = $this->checkParameters();
        

        if(!$validParams['valid'] || $validParams['msg']){
            return response()->json([
                'error' => $validParams['msg']
            ], 400);
        }

        //Perform validation on the requested params before procedding
        $sendParams = null;
        $validParams = $this->validateParameters();
        
        if(!$validParams['valid'] && $validParams['msg']){
            return response()->json([
                'error' => $validParams['msg']
            ], 400);
        }

        //Set valid parameters for request
        if($validParams['params']) $sendParams = $validParams['params'];
        if(!$sendParams){
            return response()->json([
                'error' => 'No valid parameters found. '
            ], 400);
        }

        //Set developer key
        if(!array_key_exists('key', (array) $sendParams)) $sendParams['key'] = $this->youtubeKey;

        try{
            //Prepare cURL client with configuration
            $client = new Client([
                'base_uri' => $this->configObj['Global']['url']
            ]);

            // Send a request
            $response = $client->request('GET', $this->currentEndpoint, [
                'query' => $sendParams,
                'headers' => ['Referer' => env('APP_URL'), 'Accept'     => 'application/json']
            ]);

            //Get request response
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if($responseBody){
                return response()->json([
                    'success' => $responseBody
                ], 200);
            }

        }catch(ClientException $e){
            $error = [
                'status' => 400,
                'message' => 'Error Ocurred !'
            ];
            $response = $e->getResponse();
            $responseBodyAsString = $response->getBody()->getContents();

            if(empty($responseBodyAsString) == false){
                $tmpStr = json_decode($responseBodyAsString, true);
                if($tmpStr['error']['code']) $error['status'] = $tmpStr['error']['code'];
                if($tmpStr['error']['message']) $error['message'] = $tmpStr['error']['message'];
            }
            
            return response()->json([
                'error' => $error['message']
            ], $error['status']);
        }
    }

    
}
