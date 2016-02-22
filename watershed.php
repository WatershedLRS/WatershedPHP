<?php
/*
    Copyright 2016 Watershed Systems
    Licensed under the Apache License, Version 2.0 (the "License");
    you may not use this file except in compliance with the License.
    You may obtain a copy of the License at
    http://www.apache.org/licenses/LICENSE-2.0
    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS,
    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
    See the License for the specific language governing permissions and
    limitations under the License.

Watershed client library

@module WatershedClient
*/

namespace WatershedClient;

class Watershed {

    //@class WatershedClient

    protected $endpoint;
    protected $auth;

    //look-up for card template ids
    public $cardTemplateList = array (
        "accomplishment" => 311,
        "activity detail" => 161,
        "activity stream" => 282,
        "correlation" => 261,
        "leaderboard" => 332,
        "skills" => 301,
        "group" => 281

    );

    /*
    @constructor
    @param {String} [$url] API base endpoint. 
    e.g. "https://watershedlrs.com" or "https://sandbox.watershedlrs.com" (does not include "api/")
    @param {Array} [$authCfg] Authentication details.
        @param {String} [method] Authentication method: "BASIC" only. Defaults to "BASIC".
        Later versions may support "COOKIE".
        @param {String} [header] Complete Basic HTTP Authentication header value.
        @param {String} [username] Watershed username to generate Basic HTTP Authentication 
        header value if not provided.
        @param {String} [password] Watershed password to generate Basic HTTP Authentication 
        header value if not provided.
    */
    public function __construct($url, $authCfg) {
        $this->setEndpoint($url);
        $this->setAuth($authCfg);
    }

    /*
    @method setEndpoint Sets the API endpoint to use. 
    @param {String} [$value] Endpoint url, with out without the slash at the end
    */
    public function setEndpoint($value) {
        if (substr($value, -1) != "/") {
            $value .= "/";
        }
        $this->endpoint = $value;
        return $this;
    }


    /*
    @method setAuth Sets the authentication header to use. 
    @param {Array} [$authCfg] Authentication details. See constructor.
    */
    public function setAuth($authCfg) {

        if (isset($authCfg["method"])){
            $this->auth["method"] = $authCfg["method"];
        }
        else
        {
            $this->auth["method"] = "BASIC";
        }

        switch ($this->auth["method"]) {
            //Default to BASIC. Add other supported methods here.
            default:
                if (isset($authCfg["header"])) {
                    $this->auth["header"] = $authCfg["header"];
                }
                else {
                    if (!isset($authCfg["username"])){
                        $authCfg["username"] = "";
                    }
                    if (!isset($authCfg["password"])){
                        $authCfg["password"] = "";
                    }
                    $this->auth["header"] = "Basic ".base64_encode($authCfg["username"].":".$authCfg["password"]);
                }
                break;
        }

        return $this;
    }

    /*
    @method sendRequest Sends a request to the API.
    @param {String} [$method] Method of the request e.g. POST.
    @param {String} [$path] Relative path of the resource. Does not include "/api/".
    @param {Array} [$options] Array of optional properties.
        @param {String} [content] Content of the request (should be JSON).
    @return {Array} Details of the response
        @return {String} [metadata] Raw metadata of the response
        @return {String} [content] Raw content of the response
        @return {Integer} [status] HTTP status code of the response e.g. 201
    */
    protected function sendRequest($method, $path) {
        $options = func_num_args() === 3 ? func_get_arg(2) : array();

        $url = $this->endpoint."api/".$path;

        $http = array(
            //
            // we don't expect redirects
            //
            'max_redirects' => 0,
            //
            // this is here for some proxy handling
            //
            'request_fulluri' => 1,
            //
            // switching this to false causes non-2xx/3xx status codes to throw exceptions
            // but we need to handle the "error" status codes ourselves in some cases
            //
            'ignore_errors' => true,
            'method' => $method,
            'header' => array()
        );
        
        if ($this->auth["method"] == "BASIC"){
            array_push($http['header'], 'Authorization: ' . $this->auth["header"]);
        }
        else {
            throw new \Exception("Unsupported authentication method.");
        }

        if (($method === 'PUT' || $method === 'POST') && isset($options['content'])) {
            $http['content'] = $options['content'];
            array_push($http['header'], 'Content-length: ' . strlen($options['content']));
            array_push($http['header'], 'Content-Type: application/json');
        }
        $context = stream_context_create(array( 'http' => $http ));
        $fp = fopen($url, 'rb', false, $context);
        if (! $fp) {
            throw new \Exception("Request failed: $php_errormsg");
        }
        $metadata = stream_get_meta_data($fp);
        $content  = stream_get_contents($fp);
        $responseCode = (int)explode(' ', $metadata["wrapper_data"][0])[1];

        fclose($fp);

        return array (
            "metadata" => $metadata,
            "content" => $content,
            "status" => $responseCode
        );
    }

    /*
    @method getUUID Returns a valid version 4 UUID
    @return {String} UUID
    */
    //
    // Based on code from
    // http://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
    // Taken fron TinCanPHP Copyright Rustici Software 2014
    // https://github.com/RusticiSoftware/TinCanPHP/blob/cce69fdf886945779be2684c272c0969e61096ec/src/Util.php
    //
    public static function getUUID() {
        $randomString = openssl_random_pseudo_bytes(16);
        $time_low = bin2hex(substr($randomString, 0, 4));
        $time_mid = bin2hex(substr($randomString, 4, 2));
        $time_hi_and_version = bin2hex(substr($randomString, 6, 2));
        $clock_seq_hi_and_reserved = bin2hex(substr($randomString, 8, 2));
        $node = bin2hex(substr($randomString, 10, 6));
        /**
         * Set the four most significant bits (bits 12 through 15) of the
         * time_hi_and_version field to the 4-bit version number from
         * Section 4.1.3.
         * @see http://tools.ietf.org/html/rfc4122#section-4.1.3
        */
        $time_hi_and_version = hexdec($time_hi_and_version);
        $time_hi_and_version = $time_hi_and_version >> 4;
        $time_hi_and_version = $time_hi_and_version | 0x4000;
        /**
         * Set the two most significant bits (bits 6 and 7) of the
         * clock_seq_hi_and_reserved to zero and one, respectively.
         */
        $clock_seq_hi_and_reserved = hexdec($clock_seq_hi_and_reserved);
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved >> 2;
        $clock_seq_hi_and_reserved = $clock_seq_hi_and_reserved | 0x8000;
        return sprintf(
            '%08s-%04s-%04x-%04x-%012s',
            $time_low,
            $time_mid,
            $time_hi_and_version,
            $clock_seq_hi_and_reserved,
            $node
        );
    }

    /*
    @method buildListString Takes an array of strings [x,y,z] and returns a string list "x, y and z".
    @param {Array} [$array] Array of strings. 
    @return {String} Human readable list. 
    */
    public function buildListString($array) {
        $counter = 0;
        $count = count($array);
        $returnStr = "";
        foreach ($array as $item) {
            $counter++;
            if ($counter == $count) {
                $returnStr .= $item;
            }
            elseif ($counter == ($count - 1)) {
                $returnStr .= $item." and ";
            }
            else {
                $returnStr .= $item.", ";
            }
        }
        return $returnStr;
    }

    /*
    @method buildMeasure Build a measure "object" (actually an associative array, but it will be an object in JSON).
    @param {String} [$name] Human readable name of the measure.
    @param {String} [$aggregationType] Type of aggregation. See docs for possible values. 
    @param {String} [$property] Statement property to use. Includes some additional calculated properties. See docs.
    @param {String} [$match] Value to match against the value of the statement property.
    @return {Array} Measure object formatted for card configuration.
    */
    public function buildMeasure($name, $aggregationType, $property, $match = NULL){
        $measure = array (
            "name" => $name,
            "aggregation" => array (
                "type" => $aggregationType
            ),
            "valueProducer" => array (
                "type" => "STATEMENT_PROPERTY",
                "statementProperty" => $property
            )
        );

        if (!is_null($match)) {
            $measure["valueProducer"]["type"] = "SIMPLE_IF";
            $measure["valueProducer"]["match"] = $match;
        }

        return $measure;
    }

    /*
    @method getMeasure convert a simple language measure name into a measure object. Helper function to call buildMeasure.
    @param {String} [$measureName] Name of the measure e.g. "First score", "Verb Count" or "Unique raw score count"
    @param {String} [$match] Value to match against the value of the statement property. 
        Only used with "X count" and "Unique X Count" style measures. 
    @param {String} [$measureTitle] Human readbale display of the measure, if different from the name. 
    @return {Array} Measure object formatted for card configuration.
    */
    public function getMeasure($measureName, $match = TRUE, $measureTitle = NULL) {

        if ($measureTitle == NULL) {
            $measureTitle = $measureName;
        }

        $aggregationMap = array (
            "first" => "FIRST",
            "latest" => "LAST",
            "highest" => "MAX",
            "longest" => "MAX",
            "lowest" => "MIN",
            "shortest" => "MIN",
            "average" => "AVERAGE",
            "total" => "SUM"
        );

        $propertyMap = array (
            "score" => "result.score.scaled",
            "scaled" => "result.score.scaled",
            "raw" => "result.score.raw",
            "time" => "result.durationCentiseconds",
            "statement" => "id",
            "activity" => "object.id",
            "verb" => "verb.id",
            "completion" => "result.completion",
            "success" => "result.success"
        );

        $aggregationType;
        $property;
        $match = NULL;

        $measureNameLC = strtolower($measureName);
        $measureNameArr = explode(" ", $measureNameLC);

        $lastword = substr($measureNameLC, strrpos($measureNameLC, ' ') + 1);

        if ($lastword == "count") {
            $firstword = strtok($measureNameLC, " ");
            if ($firstword == "unique") {
                $aggregationType = "DISTINCT_COUNT";
                $property = $propertyMap[$measureNameArr[1]];
            }
            else {
                $aggregationType = "COUNT";
                $property = $propertyMap[$measureNameArr[0]];
            }
        }
        else {
            $aggregationType = $aggregationMap[$measureNameArr[0]];
            $property = $propertyMap[$measureNameArr[1]];
            $match = NULL;
        }
       
        return $this->buildMeasure($measureTitle, $aggregationType, $property, $match);
    }

    /*
    @method getDimension convert a simple language dimension name into a dimension object.
    @param {String} [$dimensionName] Human readable name of the measure e.g. "month", "Activity Type" or "person"
    @return {Array} Dimension object formatted for card configuration.
    */
    public function getDimension($dimensionName) {

        //$dimensionNam is case insensitive
        $dimensionName = strtolower($dimensionName);

        $dimension = array (
            "type" => "STATEMENT_PROPERTY",
        );

        switch ($dimensionName) {
            case 'activity':
                $dimension["statementProperty"] = "object.id";
                break;

            case 'activity type':
                $dimension["statementProperty"] = "object.definition.type";
                break;

            case 'day':
            case 'week':
            case 'month':
            case 'year':
                $dimension["type"] = "TIME";
                $dimension["timePeriod"] = strtoupper($dimensionName);
                break;

            default: 
                $dimension["statementProperty"] = "actor.person.id";
                break;
        }

        return $dimension;
    }

    /*
    @method createOrganization Calls the API to create a new organization. 
    @param {String} [$name] Name of the orgaization to create. Must be unique. 
    @return {Array} Details of the result of the request
        @return {Boolean} [success] Was the request was a success?
        @return {String} [content] Raw content of the response
        @return {Integer} [status] HTTP status code of the response e.g. 201
        @return {Integer} [orgId] Id of the organization created. 
    */
    public function createOrganization($name) {
        $response = $this->sendRequest("POST", "organizations", array(
                "content" => json_encode( 
                    array(
                        "name"=> $name
                    )
                )
            )
        );

        $success = FALSE;
        if ($response["status"] === 201) {
            $success = TRUE ;
        }

        $return = array (
            "success" => $success, 
            "status" => $response["status"],
            "content" => $response["content"]
        );

        $content = json_decode($response["content"]);

        if (isset ($content->id)) {
            $return["orgId"] = $content->id;
        }
        else {
            $return["orgId"] = NULL;
        }

        return $return;
    }

    /*
    @method createActivityProvider Calls the API to create new actvity provider credentials. 
    @param {String} [$name] Name of the activity to create. 
    @param {String} [$orgId] Id of the organization to create the AP on.
    @return {Array} Details of the result of the request
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response
        @return {Integer} [status] HTTP status code of the response e.g. 201
        @return {String} [key] xAPI Basic Auth key/login
        @return {String} [secret] xAPI Basic Auth secret/password
        @return {String} [LRSEndpoint] xAPI LRS endpoint
    */
    public function createActivityProvider($name, $orgId) {
        $key = $this->getUUID();
        $secret = $this->getUUID();

        $response = $this->sendRequest("POST", "organizations/{$orgId}/activity-providers", array(
                "content" => json_encode( 
                    array(
                        "name" => $name,
                        "key" => $key,
                        "secret" => $secret,
                        "active" => TRUE,
                        "rootAccess" => TRUE
                    )
                )
            )
        );

        $success = FALSE;
        if ($response["status"] === 201) {
            $success = TRUE ;
        }

        $return = array (
            "success" => $success, 
            "status" => $response["status"],
            "content" => $response["content"],
            "key" => $key,
            "secret" => $secret,
            "LRSEndpoint" => $this->endpoint."api/organizations/{$orgId}/lrs/"
        );

        return $return;
    }

        /*
    @method deleteActivityProvider Calls the API to delete actvity provider credentials. 
    @param {String} [$id] Id of the activity to delete. 
    @param {String} [$orgId] Id of the organization to delete the AP on.
    @return {Array} Details of the result of the request
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response
        @return {Integer} [status] HTTP status code of the response e.g. 201
    */
    public function deleteActivityProvider($id, $orgId) {
        $key = $this->getUUID();
        $secret = $this->getUUID();

        $response = $this->sendRequest("DELETE", "organizations/{$orgId}/activity-providers/{$id}", array());

        $success = FALSE;
        if ($response["status"] === 200) {
            $success = TRUE ;
        }

        $return = array (
            "success" => $success, 
            "status" => $response["status"],
            "content" => $response["content"],
        );

        return $return;
    }

    /*
    @method createInvitation Calls the API to invite a user to an org. 
    @param {String} [$name] Full name of the person to invite.
    @param {String} [$email] Email address of the person to invite.
    @param {String} [$role] Role to assign: admin, owner or user. 
    @param {String} [$orgId] Id of the organization to create the invite on.
    @return {Array} Details of the result of the request
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response
        @return {Integer} [status] HTTP status code of the response e.g. 201
    */
    public function createInvitation($name, $email, $role, $orgId) {
        $response = $this->sendRequest("POST", "memberships", array(
                "content" => json_encode( 
                    array(
                        "user" => array(
                            "name" => $name,
                            "email" => $email
                        ),
                        "organization" => array(
                            "id" => $orgId
                        ),
                        "role" => $role,
                        "invitationUrlTemplate" => $this->endpoint."app/outside.html#invitation-signup/{token}"
                    )
                )
            )
        );

        $success = FALSE;
        if ($response["status"] === 201) {
            $success = TRUE ;
        }

        $return = array (
            "success" => $success, 
            "status" => $response["status"],
            "content" => $response["content"],
        );

        return $return;
    }

    /*
    @method createInvitation Calls the API to create a skill. Prerequisite for creating a skill card. 
    @param {String} [$activityName] xAPI activity name to use in the skill.
    @param {String} [$xAPIActivityId] xAPI activity id to use in the skill.
    @param {String} [$orgId] Id of the organization to create the skill on.
    @return {Array} Details of the result of the request.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
        @return {Integer} [skillId] Id of the skill created.
    */
    public function createSkill($activityName, $xAPIActivityId, $orgId) {

        $skillConfigObj = array (
            "name" => $activityName,
            "components" => array (
                array (
                    "name" => "object.id",
                    "value" => $xAPIActivityId
                )
            )
        );

        $response = $this->sendRequest("POST", "organizations/{$orgId}/skills", array(
                "content" => json_encode($skillConfigObj)
            )
        );

        $success = FALSE;
        if ($response["status"] === 201) {
            $success = TRUE ;
        }

        $return = array (
            "success" => $success, 
            "status" => $response["status"],
            "content" => $response["content"]
        );

        $content = json_decode($response["content"]);

        if (isset ($content->id)) {
            $return["skillId"] = $content->id;
        }
        else {
            $return["skillId"] = NULL;
        }

        return $return;
    }

    /*
    @method createCard Calls the API to create a card. use helper functions for specific card types. 
    @param {Array} [$configuration] Card configuration "object" (do not JSON encode!).
    @param {String} [$template] name of card template to use e.g. "leaderboard" or "Activity Detail".
    @param {String} [$title] Title of the card.
    @param {String} [$description] Decsription of the card.
    @param {String} [$summary] Summary text for the card.
    @param {String} [$orgId] Id of the organization to create the card on.
    @return {Array} Details of the result of the request.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
        @return {Integer} [cardId] Id of the card created.
    */
    public function createCard($configuration, $template, $title, $description, $summary, $orgId) {

        $response = $this->sendRequest("POST", "cards", array(
                "content" => json_encode(
                    array (
                        "configuration" => $configuration,
                        "organization" => array (
                            "id" => $orgId
                        ),
                        "template" => array (
                            "id" => $this->cardTemplateList[strtolower($template)]
                        ),
                        "title" => $title,
                        "description" => $description,
                        "summary" => $summary
                    )
                )
            )
        );

        $success = FALSE;
        if ($response["status"] === 201) {
            $success = TRUE ;
        }

        $return = array (
            "success" => $success, 
            "status" => $response["status"],
            "content" => $response["content"]
        );

        $content = json_decode($response["content"]);
        
        if (isset ($content->id)) {
            $return["cardId"] = $content->id;
        }
        else {
            $return["cardId"] = NULL;
        }

        return $return;
    }

    /*
    @method createSkillCard Calls the API to create a skill, then create a card for that skill
    @param {String} [$activityName] xAPI activity name to use in the skill.
    @param {String} [$xAPIActivityId] xAPI activity id to use in the skill.
    @param {String} [$orgId] Id of the organization to create the skill and card on.
    @return {Array} Details of the result of the request.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
        @return {Integer} [cardId] Id of the card created.
        @return {Integer} [skillId] Id of the skill created. 
    */
    public function createSkillCard($activityName, $xAPIActivityId, $orgId) {
        $response = $this->createSkill($activityName, $xAPIActivityId, $orgId);
        if ($response["success"]) {
            $skillId = $response["skillId"];

            $configuration = array(
                "filter" => array(
                    "skillIds" => array ($skillId)
                )
            );

            $response = $this->createCard(
                $configuration, 
                "skills", 
                "Practicing {$activityName}", 
                "The Skills report card tells you how often learners practice.", 
                "The Skills report card tells you how often learners practice.", 
                $orgId
            );

            $response["skillId"] = $skillId;

        } 
        return $response;
    }

    /*
    @method createActivityStreamCard Calls the API to create an activity stream card filtered by a base activity id URL.
    Uses regex to filter all activity ids starting with the activity id provided. 
    @param {String} [$activityName] xAPI activity name 
    @param {String} [$xAPIActivityId] xAPI activity id (or start of activity id)
    @param {String} [$orgId] Id of the organization to create the card on.
    @return {Array} Details of the result of the request.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
        @return {Integer} [cardId] Id of the card created. 
    */
    public function createActivityStreamCard($activityName, $xAPIActivityId, $orgId) {
        $configuration = array(
            "filter" => array(
                "activityIds" => array (
                    "ids" => array ($xAPIActivityId.".*"),
                    "regExp" => TRUE
                )
            )
        );

        $response = $this->createCard(
            $configuration, 
            "activity stream", 
            "{$activityName} Activity", 
            "The Activity Stream report card tells you what's happening now.", 
            "The Activity Stream report card tells you what's happening now.", 
            $orgId
        );

        return $response;
    }

    /*
    @method createActivityDetailCard Calls the API to create an activity detail card for a given activity id.
    @param {String} [$activityName] xAPI activity name 
    @param {String} [$xAPIActivityId] xAPI activity id
    @param {String} [$orgId] Id of the organization to create the card on.
    @return {Array} Details of the result of the request.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
        @return {Integer} [cardId] Id of the card created. 
    */
    public function createActivityDetailCard($activityName, $xAPIActivityId, $orgId) {
        $configuration = array(
            "filter" => array(
                "activityIds" => array (
                    "ids" => array ($xAPIActivityId),
                    "regExp" => FALSE
                )
            )
        );

        $response = $this->createCard(
            $configuration, 
            "activity detail", 
            "{$activityName} Detail", 
            "The Activity Detail report card enables you to explore an activity in detail.", 
            "The Activity Detail report card enables you to explore an activity in detail.", 
            $orgId
        );

        return $response;
    }

    /*
    @method createLeaderBoardCard Calls the API to create a leaderboard card for a given activity id.
    @param {Array} [$measureList] List measures to use in the leaderboard. Contains an array of measure config arrays. 
        Each measure config array has a name key, and optional match and title keys. See getMeasure above for details. 
    @param {Array} [$dimensionName] xAPI activity name 
    @param {String} [$activityName] xAPI activity name 
    @param {String} [$xAPIActivityId] xAPI activity id
    @param {String} [$orgId] Id of the organization to create the card on.
    @return {Array} Details of the result of the request.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
        @return {Integer} [cardId] Id of the card created. 
    */
    public function createLeaderBoardCard($measureList, $dimensionName, $activityName, $xAPIActivityId, $orgId) {
        $measureNames = array();
        $measures = array();
        foreach ($measureList as $measureItem) {
            if (!isset($measureItem["match"])) {
                $measureItem["match"] = NULL;
            }
            if (!isset($measureItem["title"])) {
                $measureItem["title"] = $measureItem["name"];
            }
            array_push($measures, $this->getMeasure($measureItem["name"], $measureItem["match"], $measureItem["title"]));
            array_push($measureNames, $measureItem["title"]);
        }    

        $dimensions = array(
            $this->getDimension($dimensionName)
        );

        $configuration = array(
            "filter" => array(
                "activityIds" => array (
                    "ids" => array ($xAPIActivityId),
                    "regExp" => FALSE
                )
            ),
            "dimensions" => $dimensions,
            "measures" => $measures
        );

        $description = "Use this Leaderboard to find the ";
        $description .= $this->buildListString($measureNames);
        $description .= " of each {$dimensionName}.";

        $response = $this->createCard(
            $configuration, 
            "leaderboard", 
            "{$activityName} Leaderboard", 
            $description, 
            $description, 
            $orgId
        );
        return $response;
    }

    /*
    @method createCorrelationCard Calls the API to create a correlation card for a given activity id.
    @param {Array} [$measureList] List measures to use in the leaderboard. Contains an array of measure config arrays. 
        Each measure config array has a name key, and optional match and title keys. See getMeasure above for details. 
    @param {Array} [$dimensionName] xAPI activity name 
    @param {String} [$activityName] xAPI activity name 
    @param {String} [$xAPIActivityId] xAPI activity id
    @param {String} [$orgId] Id of the organization to create the card on.
    @return {Array} Details of the result of the request.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
        @return {Integer} [cardId] Id of the card created. 
    */
    public function createCorrelationCard($measureList, $dimensionName, $activityName, $xAPIActivityId, $orgId) {
        $measureNames = array();
        $measures = array();
        foreach ($measureList as $measureItem) {
            if (!isset($measureItem["match"])) {
                $measureItem["match"] = NULL;
            }
            if (!isset($measureItem["title"])) {
                $measureItem["title"] = $measureItem["name"];
            }
            array_push($measures, $this->getMeasure($measureItem["name"], $measureItem["match"], $measureItem["title"]));
            array_push($measureNames, $measureItem["title"]);
        }   

        $dimensions = array(
            $this->getDimension($dimensionName)
        );

        $configuration = array(
            "filter" => array(
                "activityIds" => array (
                    "ids" => array ($xAPIActivityId),
                    "regExp" => FALSE
                )
            ),
            "dimensions" => $dimensions,
            "measures" => $measures
        );

        $description = "Use this Correlation to explore relationships between the ";
        $description .= $this->buildListString($measureNames);
        $description .= " of each {$dimensionName}.";

        $response = $this->createCard(
            $configuration, 
            "correlation", 
            "{$activityName} Correlation", 
            $description, 
            $description, 
            $orgId
        );
        return $response;
    }

    /*
    @method groupCards Makes a series of API calls to put a list of cards into a group.
    @param {Array} [$cardIds] List of integer card ids. 
    @param {String} [$orgId] Id of the organization to create the card on.
    @param {String} [$cardGroupName] Unqiue name of the card group.
    @param {String} [$cardGroupTitle] Display title of the card group.
    @param {String} [$parentGroupName] Name of the card group the cards are currently in, if not ws-activity.
        New cards created by an admin or owner account are added to ws-activity by default. 
    @return {Array} Details of the result of the series of requests.
        @return {Boolean} [success] Was the request was a success? 
        @return {Integer} [groupId] Id of the group created. 
        @return {Integer} [cardId] Id of the card created. 
    */
    public function groupCards($cardIds, $orgId, $cardGroupName, $cardGroupTitle, $parentGroupName = NULL) {
        if ($parentGroupName == NULL) {
            $parentGroupName = "ws-activity";
        }

        $parentGroupId;
        $startingCards;
        $newCards;
        $cardId;
        $groupId;

        //get the parent group id
        $response = $this->sendRequest(
            "GET", 
            "organizations/{$orgId}/card-groups/?name={$parentGroupName}"
        );

        if ($response["status"] == 200) {
            $responseContent = json_decode($response["content"], TRUE);
            $parentGroupId = $responseContent["results"][0]["id"];
            $startingCards = $responseContent["results"][0]["cardIds"];
        }
        else {
            return $response;
        }

        //create card group
        $response = $this->createCardGroup($cardGroupName, $cardIds, $orgId);

        if ($response["success"]) {
            $groupId = $response["groupId"];
        }
        else {
            return $response;
        }

        //create group card to display cards
        $response = $this->createCard(
            array (
                "cardGroupId"=> $groupId
            ), 
            "group", 
            $cardGroupTitle, 
            NULL, 
            NULL, 
            $orgId
        );
        if ($response["success"]) {
            $cardId = $response["cardId"];
        }
        else {
            return $response;
        }

        //hide grouped cards from parent group
        $response = $this->hideGroupedCards($startingCards, $cardIds, $cardId, $parentGroupId, $parentGroupName, $orgId);
        if (!$response["success"]) {
            return $response;
        }

        //return group id and card id
        return array (
            "success" => TRUE,
            "groupId" => $groupId,
            "cardId" => $cardId
        );
    }

    /*
    @method createCardGroup Uses the API to create a group of cards.
    @param {String} [$cardGroupName] Unqiue name of the card group.
    @param {Array} [$cardIds] List of integer card ids. 
    @param {String} [$orgId] Id of the organization to create the card on.
    @return {Array} Details of the result of the series of requests.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
        @return {Integer} [groupId] Id of the group created. 
    */
    public function createCardGroup ($cardGroupName, $cardIds, $orgId) {
        $response = $this->sendRequest(
            "POST", 
            "card-groups", 
            array (
                "content" => 
                json_encode( 
                    array(
                        "name" => $cardGroupName,
                        "cardIds" => $cardIds,
                        "organization" => array (
                            "id" => $orgId
                        )
                    )
                )
            )
        );

        $success = FALSE;
        if ($response["status"] === 201) {
            $success = TRUE ;
        }

        $return = array (
            "success" => $success, 
            "status" => $response["status"],
            "content" => $response["content"]
        );

        $content = json_decode($response["content"]);
        
        if (isset ($content->id)) {
            $return["groupId"] = $content->id;
        }
        else {
            $return["groupId"] = NULL;
        }

        return $return;
    }

    /*
    @method hideGroupedCards removes a set of cards from a group and adds a card to that group. 
    Designed to be used to remove a set of cards that have been grouped and add the group card. 
    @param {Array} [$startingCards] List of integer card ids. 
        Those cards which are in the group to begin with (not including the group card to add). 
    @param {Array} [$groupedCards] List of integer card ids. 
        Those cards which are to be removed from the group. (I.e. those that have been added to the new group)
    @param {Integer} [$groupCardId] Id of card to add to the group (I.e. the group card)
    @param {String} [$parentGroupId] Id of the card group to be editted.
    @param {String} [$parentGroupName] Name of the card group to be editted.
    @param {String} [$orgId] Id of the organization the group exists in.
    @return {Array} Details of the result of the series of requests.
        @return {Boolean} [success] Was the request was a success? 
        @return {String} [content] Raw content of the response.
        @return {Integer} [status] HTTP status code of the response e.g. 201.
    */
    public function hideGroupedCards ($startingCards, $groupedCards, $groupCardId, $parentGroupId, $parentGroupName, $orgId){
        $newCards = array_diff($startingCards, $groupedCards);
        array_push($newCards, $groupCardId);

        $response = $this->sendRequest(
            "PUT", 
            "card-groups/{$parentGroupId}", 
            array (
                "content" => json_encode(
                    array(
                        "name" => $parentGroupName,
                        "cardIds" => $newCards,
                        "organization" => array (
                            "id" => $orgId
                        )
                    )
                )
            )
        );

        $success = FALSE;
        if ($response["status"] === 204) {
            $success = TRUE ;
        }

        return array (
            "success" => $success, 
            "status" => $response["status"],
            "content" => $response["content"]
        );
    }
}
