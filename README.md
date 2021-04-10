This script convert multiple vcard/vcf file sinto one You can choos eif you want search occurrences or not.

# Installation

Use composer to get the package:

>composer require sabsab43/vcard-fusion

Available on Packagist: https://packagist.org/packages/sabsab43/vcard-fusion

# Configuration

You just need this code in your controller

```php

use VcardFusion\VcardManager;

/** Your code .... **/

if (isset($_FILES)) 
{   
    $vcm = new VcardManager($_FILES);
    $errors = $vcm->mergeVcardFiles();  
}

```

Occurrences search is active if user would use it, for this you need to use checkbox.

The VcardManager class constructor wait this:

```php
$_POST['version'] //'2.1', '3.0' or '4.0'
$_POST['checkOccurrences'] // true or false
$_POST['checkVersion'] //true or false
```

Form example

```html
  <form action="./indexController.php" enctype="multipart/form-data" method="POST">
      <h2>Choose vcard version</h2> 
      <div>
          <input type="radio" id="vcard2" name="version" value="2.1">
          <label for="vcard2">Vcard 2.1</label>

          <input type="radio" id="vcard3" name="version" value="3.0" checked>
          <label for="vcard3">Vcard 3.0</label>

          <input type="radio" id="vcard4" name="version" value="4.0">
          <label for="vcard4">Vcard 4.0</label>
      </div>

      <h2>Select your options</h2>
      <div>
          <div>
              <input type="checkbox" name="checkOccurrences" id="checkOccurrences">
              <label for="checkOccurrences">Check occurrences</label>
          </div>

          <div>
              <input type="checkbox" name="checkVersion" id="checkVersion" checked>
              <label for="checkVersion">Check Vcard version(<strong>recommandé</strong>)</label>
          </div>
      </div>

      <h2>Select your files</h2>
      <div>
          <div>
              <input type="file" name='files[]' multiple="multiple">
          </div>
          <div>
              <button type="submit">Merge</button>
          </div>
      </div>
  </form>
```
