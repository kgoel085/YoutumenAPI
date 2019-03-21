<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class EndpointController extends Controller
{
    private $requestObj;
    private $configObj;
    private $currentAction = null;
    private $currentEndpoint = null;
    private $youtubeKey = null;

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
            }
        }
    }

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

    public function checkParameters(){
        $returnArr = array('Status' => true, 'msg' => array(), 'params' => array());

        $receivedParams = $this->requestObj->all();
        $allowedParams = array_keys($this->configObj['EndpointConfig'][$this->currentAction]['params']);

        if(count($receivedParams) > 0){
            foreach($receivedParams as $reqKeys => $reqVars){
                //if current param is not in allowed variables
                if(!in_array($reqKeys, $allowedParams)){
                    $returnArr['msg'][] = $reqKeys;
                }else{
                    //Add the params in the array to send
                    if(empty($reqVars) == false) $returnArr['params'][$reqKeys] = $reqVars;
                }
            }
        }

        if(count($returnArr['msg']) > 0){
            $returnArr['Status'] = false;
            $returnArr['msg'] = implode(', ', array_unique($returnArr['msg']));
            $returnArr['params'] = array();
        }else{
            $returnArr['Status'] = true;
            
            //dd($returnArr['params']);
            if(array_key_exists('common', $this->configObj['EndpointConfig'][$this->currentAction])){
                $commonVars = $this->configObj['EndpointConfig'][$this->currentAction]['common'];
                foreach($commonVars as $commVariable){
                    $arreyKeyExists = array_key_exists($commVariable, $returnArr['params']);
                    if(!$arreyKeyExists){
                        $returnArr['params'][$commVariable] = $this->configObj['EndpointConfig'][$this->currentAction]['params'][$commVariable];
                    }
                }
            }
        }

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
     * Perform validations on the parameters before sending them to the API endpoint
     */
    function validateParameters($paramArr = array()){
        if(count($paramArr) == 0 || !is_array($paramArr)) return $paramArr;
        $errorArr = array();

        $currentAction = $this->currentAction;
        $paramKeys = array_keys($paramArr);

        //Create case for any endpoint validation you want
        switch($currentAction){
            // Search Endpoint
            case 'search':
                //Check for dates
                if(in_array('publishedAfter', $paramKeys)){
                    $validDate = $this->validateDate($paramArr['publishedAfter']);
                    if(!$validDate) $errorArr[] = 'Invalid Date in publishedAfter';
                }

                if(in_array('publishedBefore', $paramKeys)){
                    $validDate = $this->validateDate($paramArr['publishedBefore']);
                    if(!$validDate) $errorArr[] = 'Invalid Date in publishedBefore';
                }

                //Check for search content type
                if(in_array('videoType', $paramKeys) && in_array('type', $paramKeys)){
                    $videoType = $type = "";
                    if(array_key_exists('videoType', $paramArr)) $videoType = $paramArr['videoType'];
                    if(array_key_exists('type', $paramArr)) $type = $paramArr['type'];

                    $excludeArr = array('video', 'channel', 'playlist');
                    if(in_array(strtolower($type), $excludeArr) && empty($videoType) == false){
                        unset($paramArr['videoType']);
                    }
                }
            break;

            default:
                //Check for maxResult value
                if(in_array('maxResults', $paramKeys) && ($paramArr['maxResults'] > 50 || empty($paramArr['maxResults'])) ) $paramArr['maxResults'] = 10;
            break;
        }

        return $paramArr;
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
        if(!$validParams['Status'] || $validParams['msg']){
            return response()->json([
                'error' => 'Following parameters are not allowed. Invalid parameters: '.$validParams['msg']
            ], 400);
        }

        $queryParams = $validParams['params'];
         //Run any validations on the final api parameters array
         $queryParams = $this->validateParameters($queryParams);

         echo json_encode($queryParams);

        if(!array_key_exists('key', $queryParams)) $queryParams['key'] = $this->youtubeKey;
        if(!array_key_exists('part', $queryParams)) $queryParams['part'] = 'snippet';

        try{
            //Prepare cURL client with configuration
            $client = new Client([
                'base_uri' => $this->configObj['Global']['url']
            ]);

            // Send a request
            $response = $client->request('GET', $this->currentEndpoint, [
                'query' => $queryParams,
                'headers' => ['Referer' => env('APP_URL'), 'Accept'     => 'application/json']
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);
            
            $excludeFields = $this->configObj['EndpointConfig']['excludeFields'];

            foreach($excludeFields as $excludeKEys => &$excludeType){
                if(is_array($excludeType)){
                    foreach($responseBody['items'] as &$item){
                        foreach($excludeType as $excludeItems){
                            if($item[$excludeItems]) unset($item[$excludeItems]);
                        }
                    }
                }else{
                    if($responseBody[$excludeKEys]) unset($responseBody[$excludeKEys]);
                }
            }

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
