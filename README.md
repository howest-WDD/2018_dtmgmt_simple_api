# simple-php-api
This API was created to teach at Howest in the course module "Datamanagement"
## Getting started
Place the "extern" folder at a location of your choosing. Rename it, if you want to. This will be the location where your api will live e.g. http://yourwebsite.com/extern/api.php .

Do not forget to change the settings for the API in the extern/api.config.php
```
// Database configuratie
$db['host']="localhost";
$db['user']="cars_reader";
$db['pw']="mypassword";
$db['name']="cars";
$db['port']=3307;

$allowCors=true;
```
Place the content of the "website" folder inside of your website and include the script file(s) to be able to call the api.


## Deployment
Cors is allowed by default, you can change this in the api.config.php file.

## Calling the API
You can call the API using the website/script files.
```
//performing a POST operation

//the object
$vehicle['make']="MyMake";
$vehicle['brand']="MyBrand";

// the call itself
$vehicleTypes = CallAPI("POST","http://yourwebsite.com/extern/api.php/vehicles",json_encode($vehicle));
```
```
//performing a GET operation
$vehicleTypes = CallAPI("GET","http://yourwebsite.com/extern/api.php/vehicles");
```
```
//performing a PATCH/PUT operation

//the object
$vehicle['make']="MyMake";
$vehicle['brand']="MyBrand";

// the call itself
$vehicleTypes = CallAPI("PATCH","http://yourwebsite.com/extern/api.php/vehicles/{id}",json_encode($vehicle));
```

```
//performing a DELETE operation
$vehicleTypes = CallAPI("DELETE","http://yourwebsite.com/extern/api.php/vehicles/{id}");
```