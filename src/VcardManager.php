<?php

namespace VcardFusion;

/**
 * Merges two or multiple Vcard files and return an unique Vcard file 
 */
class VcardManager
{    
    /**
     *
     * @var array
     */
    private $files;    

    /**
     *
     * @var int
     */
    private $nbFiles;    

    /**
     *
     * @var string
     */
    private $tempPath;
    
    /**
     *
     * @var string
     */
    private $finalVcardName;
        
    /**
     *
     * @var string
     */
    private $version;
    
    /**
     *
     * @var bool
     */
    private $checkVersion;
    
    /**
     *
     * @var bool
     */
    private $checkOccurrences;


    public function __construct(array $files = [])
    {
        $this->files = $files['files'];
        $this->nbFiles = count($files['files']['name']);
        $this->tempPath =  dirname(__DIR__) . DIRECTORY_SEPARATOR . "tmp" . DIRECTORY_SEPARATOR;
        $this->finalVcardName = uniqid() . '-vcard';
        $this->version = isset($_POST['version']) ? $_POST['version'] : null;
        $this->checkVersion = isset($_POST['checkVersion']) ? true : false;
        $this->checkOccurrences = isset($_POST['checkOccurrences']) ? true: false;
    }

    /**
     * Checks data sends by User, return an empty array error if all files are goods.
     *
     * @return array $errors - Returns an errors array, it's empty if files are correct
     */
    public function checkRequest(): array
    {
        $errors = [];

        if ($this->version === null) {
            $errors[] = "Aucune version n'a été renseignée.";
            return $errors;
        }    

        if ($this->files['name'][0] == "") {
            $errors[] = "Aucun fichier n'a été envoyé.";
            return $errors;
        }

        for ($i = 0; $i < $this->nbFiles; $i++) {

            if ($this->files['size'][$i] <= 0) {
                $errors[] = "Le fichier \"" . $this->files['name'][$i] . "\" est vide.";
                continue;
            }

            if ($this->files['type'][$i] !== "text/vcard") {
                $errors[] = "Le fichier \"" . $this->files['name'][$i] . "\" doit être au format .vcf ou .vcard.";
            }
        }
        return $errors;
    }

    /**
     * Merges all Vcard Files into one Vcard file,it has checking occurrences and checking Vcards Versions options
     *
     * @return array $errors - If the convertion failed, returns all errors encountered 
     */
    public function mergeVcardFiles(): array
    {
        // Checks data sent by user
        $errors = $this->checkRequest();
        if (!empty($errors)) { return $errors; }

        // Creates vcardItems from vcard files
        $vcardsArray = $this->convertVcardFilesToVcardsItems();
        $vcardItems = $this->vcardsItemsGenerator($vcardsArray);

        // Checks versions and occurrences if user chooses it
        if ($this->checkVersion) { $errors = $this->checkVersion($vcardItems); }
        if (!empty($errors)) { return $errors; }
        if ($this->checkOccurrences)
        {
            /** @var array */
            $occurrencesArray = $this->searchOccurrences($vcardItems);
            if (!empty($occurrencesArray)) 
            {
                $vcardItems = $this->mergeOccurrences($occurrencesArray, $vcardItems);
            }
        }

        // Creates and send new vcard file to user, return errors if it failed
        $finalVcardContent = $this->setFinalContentVcard($vcardItems);        
        if (empty($errors)) { $this->sendNewVcard($finalVcardContent); }
        return $errors;
    }

    
    /**
     * Checks if all Vcards have same version, return an error if they don't have same version.
     *
     * @param  array $vcardItems
     * @return array $errors
     */
    private function checkVersion(array $vcardItems): array
    {
        // Checking vcards version
        $errors = [];
        $badVersionFiles = [];
        foreach ($vcardItems as $vcardItem)
        {
            $alreayInBadVersionfiles = false;
            foreach ($badVersionFiles as $file)
            {
                if ($file['name'] === $vcardItem->getFilename()) 
                {
                    $alreayInBadVersionfiles = true;
                }
            }

            if($this->version != $vcardItem->getVersion() &&  !$alreayInBadVersionfiles) 
            {
                $badVersionFiles[] = ["name" => $vcardItem->getFilename(), "version" => $vcardItem->getVersion()];
            }
        }

        // creates an error for all files with not good version
        if (!empty($badVersionFiles)) {
            $error = "Les fichiers Vcard suivants sont au mauvais format (format attendu : Vcard$this->version):<br>";

            foreach ($badVersionFiles as $file) 
            {
                $error = $error."- ".$file['name']." (Vcard".$file['version'].")<br>";
            }
            $errors[] = $error;
        }
        return $errors;
    }

    /**
     * Converts each contact in sent Vcard File(s) into one VcardItem instance
     *
     * @return array $vcardsArray - [vcardArray1 => ["filename" => filename, "data" => [], ..., vcardArrayN => ...]
     */
    private function convertVcardFilesToVcardsItems(): array
    {
        $vcardsArray = [];
        for ($i = 0; $i < $this->nbFiles; $i++) {
            $vcardArray = [
                "filename" => $this->files['name'][$i],
                "data" => file(
                    $this->files['tmp_name'][$i],
                    FILE_IGNORE_NEW_LINES
                )
            ];
            $vcardsArray[] = $vcardArray;
        }
        return $vcardsArray;
    }

    /**
     * Creates a VcardItem instance for each contact in files and return an array of them
     *
     * @param  array $vcardsArray
     * @return array $vcardItems - [0 => vcardItem1, ..., N => vcardItemN]
     */
    private function vcardsItemsGenerator(array $vcardsArray): array
    {
        $vcardItems = [];
        foreach ($vcardsArray as $vcardArray) 
        {
            foreach ($vcardArray["data"] as $vcardLine) 
            {
                if (!isset($vcardItem)) 
                {
                    $vcardItem = new VcardItem($vcardArray["filename"]);
                }
                else 
                {
                    $vcardItem->setData($vcardLine);
                }

                switch ($vcardLine) {
                    case $vcardLine === "BEGIN:VCARD":
                        $vcardItem->setData($vcardLine);
                        break;

                    case substr($vcardLine, 0, 8) === "VERSION:":
                        $vcardItem->setVersion(substr($vcardLine, -3));
                        break;

                    case substr($vcardLine, 0, 3) === "FN:":
                        $vcardItem->setFn($vcardLine);
                        break;

                    case substr($vcardLine, 0, 2) === "N:":
                        $vcardItem->setN($vcardLine);
                        break;

                    case $vcardLine === "END:VCARD":
                        $vcardItems[] = $vcardItem;
                        unset($vcardItem);
                        break;
                }
            }
        }
        return $vcardItems;
    }

    /**
     * Searches all occurrences in a VcardItems array
     *
     * @param  array $vcardItems
     * @return  array $occurencesArray - ["vcard1" => vcardItem1, "vcard2" => vcardItem2, ..., "vcardN" => vcardItemN] 
     */
    private function searchOccurrences(array $vcardItems): array
    {
        $vcardItemsLength = count($vcardItems);
        $occurencesArray = [];
        
        if ($vcardItemsLength <= 1) { return $occurencesArray; }
        
        $k = 0;
        $isAnOccurrence = false;

        // FN property is not required in vcard2.1 version and N property is not required in vcard4.0
        // We need to choose the good property to compare vcards
        switch ($this->version)
        {
            case '2.1':
                $getName="getN";
                break;

            case '3.0' || '4.0':
                $getName = "getFn";
                break;
        }

        // Occurrences search
        for ($i = 0; $i < $vcardItemsLength; $i++)
        {
            $occurrence = ["vcard1" => $vcardItems[$i]];
            $l = 2;
            for ($j = 1 + $k; $j < $vcardItemsLength; $j++)
            {
                if ($vcardItems[$i]->getIsAnOccurrence()){ continue; }                

                if ($vcardItems[$i]->$getName() === $vcardItems[$j]->$getName())
                {
                    $occurrence = array_merge($occurrence, ["vcard$l" => $vcardItems[$j]]);
                    $vcardItems[$j]->setIsAnOccurrence(true);
                    $isAnOccurrence =true;
                    $l++;
                }
            }
            $k++;
            
            if ($isAnOccurrence) 
            { 
                $vcardItems[$i]->setIsAnOccurrence(true);
                $occurencesArray[] =  $occurrence;
                $isAnOccurrence = false;
            }
        }
        return $occurencesArray;
    }
    
    /**
     * Merges all occurrences and return a new updated vcardItems array
     *
     * @param  array $occurencesArray - ["vcard1" => vcardItem1, "vcard2" => vcardItem2, ..., "vcardN" => vcardItemN] 
     * @param  array $vcardItems - vcardItems array
     * @return array $ newVcardItems - Updated vcardItems array
     */
    private function mergeOccurrences(array $occurencesArray, array $vcardItems): array
    {
        // Merges all occurrences
        foreach ($occurencesArray as $occurrences) 
        {
            // Checks all lines of each vcard and put them in a array if it's not an occurence
            $dataToMerge = [];
            $nbOccurrences = count($occurrences);
            for ($i=1; $i < $nbOccurrences + 1; $i++)
            {
                $vcardData = $occurrences["vcard$i"]->getData();
                $vcardDataLength = count($vcardData);
                $dataToMergeLength = count($dataToMerge);

                for ($j=0; $j < $vcardDataLength - 1; $j++)
                { 
                    $isAnOccurrence = false;
                    for ($k=0; $k < $dataToMergeLength; $k++)
                    {
                        if ($vcardData[$j] === $dataToMerge[$k])
                        {
                            $isAnOccurrence = true;
                            continue;
                        }
                    }

                    if (!$isAnOccurrence && !empty($vcardData[$j]))
                    { 
                        $dataToMerge[] = $vcardData[$j];
                    }
                }
            }
            $dataToMerge[] = "END:VCARD";
            
            // Creates a new vcardItem with all uniques properties of each old vcards
            $dataToMergeLength = count($dataToMerge);
            for ($i=0; $i < $dataToMergeLength; $i++)
            { 
                $entry = $dataToMerge[$i];
                switch ($entry)
                {
                    case substr($entry, 0, 3) === "FN:":
                        $fn = $entry;
                        break;
                            
                    case substr($entry, 0, 2) === "N:":
                        $n = $entry;
                        break;
                }
            }

            $vcardItem = new VcardItem($this->finalVcardName);            
            $vcardItem->setVersion("VERSION:".$this->version);
            if (isset($fn)){ $vcardItem->setFn($fn); }
            if (isset($n)){ $vcardItem->setN($n); }
            foreach ($dataToMerge as $line)
            {
                $vcardItem->setData($line);
            }

            $vcardItems[] = $vcardItem;     
        }

        // Create a new VcardItems array with new vcards and delete old occurrences
        $newVcardItems = [];
        foreach($vcardItems as $vcardItem)    
        {
            if (!$vcardItem->getIsAnOccurrence()){ $newVcardItems[] = $vcardItem; }
        }

        return $newVcardItems;
    }
    
    /**
     * Create a string variable who contain all vcardItems
     *
     * @param  array $vcardItems - array of vcardItem instances
     * @return string $finalVcardContent - finale content of vcard
     */
    private function setFinalContentVcard(array $vcardItems): string
    {
        $finalVcardContent = "";
        /** @var VcardItem */
        foreach ($vcardItems as $vcardItem)
        {
            $finalVcardContent = $finalVcardContent.$vcardItem->stringifyData();
        }
        return $finalVcardContent;
    }

    /**
     * Sends the final Vcard file to user and stop the script
     *
     * @param  string $finalVcardContent
     * @return void
     */
    private function sendNewVcard(string $finalVcardContent)
    {
        file_put_contents($this->tempPath.$this->finalVcardName.".vcf", $finalVcardContent);
        
        header('Content-Type: text/plain');
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=\"".$this->finalVcardName.".vcf\"");
        header('Content-Length: '.filesize($this->tempPath.$this->finalVcardName.".vcf"));        
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($this->tempPath.$this->finalVcardName.".vcf");
        unlink($this->tempPath.$this->finalVcardName.".vcf");

        exit;
    }
}
