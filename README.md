# WatershedPHP
PHP Library for interacting with the Watershed API

## Introduction
Use this PHP code library to interact with the Watershed API to manage organizations, users and report cards. 

This library provides a simplified way to interact with the parts of the Watershed API most commonly required by
partner and customer applications. Please contact (Watershed support)[https://watershedlrs.zendesk.com/hc/en-us/requests/new] 
for help implmenting this library or to request coverage of other parts of the API. 

Pull Requests and contributions of libraries in other languages are most welcome. 

To interact with Watershed via xAPI (for example to send tracking data), please 
use (TinCanPHP)[http://rusticisoftware.github.io/TinCanPHP/].

## Installation
To install the library, simply include watershed.php in your project. 

```php
include ("watershed.php");
```

## Usage

### Instantiate the class
To interact with the library, first create an instance of the Watershed class. 
The examples below show instances created to conenct to Watershed sandbox and production servers. You
can either provide your Watershed API username and password (example 1), or provide a complete authentication 
header to use (example 2). 

Example 1:
```php
$auth = array(
    "method" => "BASIC",
    "username" => "aladin@example.com",
    "password" => "open sesame"
);

$wsclient = new \WatershedClient\Watershed("https://sandbox.watershedlrs.com", $auth);
```

Example 2:
```php
$auth = array(
    "method" => "BASIC",
    "header" => "Basic QWxhZGRpbjpvcGVuIHNlc2FtZQ==",
);

$wsclient = new \WatershedClient\Watershed("https://watershedlrs.com", $auth);
```

### Create an organization
Each Watershed customer has their own organization. Watershed partner applications may have permission to create
organizations. The name of the organization must be unique. 

```php
$orgName = "Name of Organization";

$response = $wsclient->createOrganization($orgName);
  if ($response["success"]) {
      $orgId = $response["orgId"];
      echo ("Org '".$orgName."'' created with id ".$orgId.".<br/>");
  } 
  else {
      echo "Failed to create org {$orgName}. Status: ". $response["status"].". The server said: ".$response["content"]."<br/>";
  }
```

### Invite a user to an organization
Invite a user with a given email address to the organization. Possible roles are:

* owner
* admin
* user

Admins and owners are able to create reports; users are only able to view reports. 

```php
$orgId = 12345;
$userName = "Aladdin"
$userEmail = "aladdin@example.com"
$role = "admin";

$response = $wsclient->createInvitation($userName, $userEmail, $role, $orgId);
if ($response["success"]) {
    echo "Invite for {$userName} &lt;{$userEmail}&gt; sent.<br/>";
} 
else {
    echo "Invite for {$userName} &lt;{$userEmail}&gt; was not created. The server said: ".$response["content"]."<br/>";
}
```

### Create a set of xAPI Activity Provider Credentials
Use these details to interact with Watershed via xAPI.

```php
$orgId = 12345;
$APName = "Name of activity provider.";
$response = $wsclient->createActivityProvider($APName, $orgId);
if ($response["success"]) {
    $key = $response["key"];
    $secret = $response["secret"];
    $endpoint = $response["LRSEndpoint"];
    echo "Activity Provider created with key {$key} and secret {$secret}. Endpoint: {$endpoint} <br/>";
} 
else {
    echo "Failed to create Activity Provider. Status: ". $response["status"].". The server said: ".$response["content"]."<br/>";
}
```
