This script convert multiple vcard/vcf file sinto one.

You can choose if you want search occurrences or not.

# Read this before use the script

Before start convertion, like with any other software, __keep a save of your vcards files__, especially if you use occurrences search option.

The occurrence search option will compare FN (or N depending on the vcard version) property, if you have two different contacts with same FN or N property, they will be merge.

for example, this two differents vcards with same name (FN property):

```
BEGIN:VCARD
VERSION:3.0
PRODID:-//dmfs.org//mimedir.vcard//EN
UID:811bd877-a93f-4e76-8098-b61ece5c8650
EMAIL;TYPE=*:john.doe@mail.com
FN:John
REV:20210330T101608Z
TEL;TYPE=VOICE,CELL:+33 1 23 45 67 89
END:VCARD

BEGIN:VCARD
VERSION:3.0
PRODID:-//dmfs.org//mimedir.vcard//EN
UID:811bd877-a93f-4e76-8098-b61ece5c8650
EMAIL;TYPE=*:john.peter@mail.fr
FN:John
REV:20210330T101608Z
TEL;TYPE=VOICE,CELL:+33 9 87 65 43 21
END:VCARD
```
they will become

```
BEGIN:VCARD
VERSION:3.0
PRODID:-//dmfs.org//mimedir.vcard//EN
UID:811bd877-a93f-4e76-8098-b61ece5c8650
EMAIL;TYPE=*:john.doe@mail.com
FN:John
REV:20210330T101608Z
TEL;TYPE=VOICE,CELL:+33 1 23 45 67 89
EMAIL;TYPE=*:john.peter@mail.fr
TEL;TYPE=VOICE,CELL:+33 9 87 65 43 21
END:VCARD
```

So make sure you have different names for your contacts if you don't want this result or disallow checking occurrences option.

# Installation

Use composer to get the package

>composer require sabsab43/vcard-fusion

It is vailable on Packagist: https://packagist.org/packages/sabsab43/vcard-fusion

# Configuration

You just need to put this code in your controller

```php
/** Your autoloader... **/
require('./vendor/autoload.php');

use VcardFusion\VcardManager;

/** Your code .... **/

$errors = [];
if (isset($_FILES) && !empty($_FILES))
{   
    $vcm = new VcardManager($_FILES);
    $errors = $vcm->mergeVcardFiles();  
}

/** Your code...**/

```

The VcardManager class constructor wait this POST request values

```php
$_POST['version'] //'2.1', '3.0' or '4.0', if not specify return an error
$_POST['checkOccurrences'] // true or false, if not specify set $checkOccurrences to false
$_POST['checkVersion'] //true or false, if not specify set $checkVersion to false
```

Searching occurrences and checking vcard versions are set by user.
You need to specify this options in your form like in this example:

```html
   <form class="form" action="./Your_Controller.php" enctype="multipart/form-data" method="POST">
    
        <fieldset>
            <legend>Vcard version</legend>
            <div>
                <input type="radio" id="vcard2" name="version" value="2.1">
                <label for="vcard2">Vcard 2.1</label>
    
                <input type="radio" id="vcard3" name="version" value="3.0" checked>
                <label for="vcard3">Vcard 3.0</label>
    
                <input type="radio" id="vcard4" name="version" value="4.0">
                <label for="vcard4">Vcard 4.0</label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Options</legend>
            <div>
                <div>
                    <input type="checkbox" name="checkOccurrences" id="checkOccurrences">
                    <label for="checkOccurrences">Check occurrences</label>
                </div>
    
                <div>
                    <input type="checkbox" name="checkVersion" id="checkVersion" checked>
                    <label for="checkVersion">Check files version(<strong>recommended</strong>)</label>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Select your files</legend>
                <?php if(isset($errors) && !empty($errors)):?>
                    <div>
                        <?php foreach ($errors as $error):
                            echo "<p style='color: red;'>$error</p>";
                        endforeach ?>
                    </div>
                   <?php endif ?>
                <div>
                    <input type="file" accept=".vcf, .vcard" name='files[]' multiple="multiple">
                </div>
        </fieldset>

        <div>
            <button class="btn-custom" type="submit">Merge</button>
        </div>
    </form>
```

The script will return a new vcard file or print errors if it failed.
