<?php

namespace VcardFusion;

use ArrayAccess;

/**
 * Merges two or multiple Vcard files and return an unique Vcard file 
 */
class VcardManager
{
    private $files;
    private $nbFiles;
    private $tempPath;
    private $finalVcardName;
    private $version;
    private $checkVersion;
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

        for ($i = 0; $i < $this->nbFiles; $i++) {

            if ($this->files['name'][$i] == "") {
                $errors[] = "Aucun fichier n'a été envoyé.";
                return $errors;
            }

            if ($this->files['size'][$i] <= 0) {
                $errors[] = "Le fichier \"" . $this->files['name'][$i] . "\" est vide.";
                return $errors;
            }

            if (!substr($this->files['name'][$i], -4) === ".vcf" || !substr($this->files['name'][$i], -6) === ".vcard") {
                $errors[] = "Le fichier \"" . $this->files['name'][$i] . "\" doit être au format .vcf ou .vcard.";
            }
        }
        return $errors;
    }

    /**
     * Merges all Vcard Files into one Vcard file,it has checking occurrences and checking Vcards Versions options
     *
     * @return array $errors - If the convertion failed, returns all errors encountered, else it returns an empty array 
     */
    public function mergeVcardFiles(): array
    {
        $errors = $this->checkRequest();
        if (!empty($errors)) { return $errors; }
        
        $vcardsArray = $this->convertVcardFilesToVcardsItems();
        $vcardItems = $this->vcardsItemsGenerator($vcardsArray);
        $errors = [];

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

        $finalVcardContent = $this->setFinalContentVcard($vcardItems);
        
        if (empty($errors)) { $errors = $this->sendNewVcard($finalVcardContent); }

        return $errors;
    }

    /**
     * Checks if all Vcards have same version, return an error if they don't have same version.
     *
     */
    private function checkVersion(array $vcardItems): array
    {
        $errors = [];
        $badVersionFiles = [];
        foreach ($vcardItems as $vcardItem)
        {
            foreach ($badVersionFiles as $file) {
                if ($file === $vcardItem->getFilename()) {
                    continue;
                }
            }

            switch ($this->version) 
            {
                case $this->version != $vcardItem->getVersion():
                    $badVersionFiles[] = $vcardItem->getFilename()." (Vcard".$vcardItem->getVersion().")";
                    break;
            }
        }

        if (!empty($badVersionFiles)) {
            $error = "Les fichiers Vcard suivants sont au mauvais format (format attendu : Vcard$this->version):<br>";

            foreach ($badVersionFiles as $file) {
                $error = $error."- $file<br>";
            }
            $errors[] = $error;
        }
        return $errors;
    }

    /**
     * Converts each contact in sent Vcard File(s) into one VcardItem instance
     *
     * @return array
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
     * @return array $vcardItems
     */
    private function vcardsItemsGenerator(array $vcardsArray): array
    {
        $vcardItems = [];
        foreach ($vcardsArray as $vcardArray) {

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
     * @return array $occurrencesArray - [0 => [vcard1], [vcard2]...[vcardn], 1 => [vcard1], [vcard2]...[vcardn], ...]
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
     * @param  array $occurencesArray - ["vcard1" => vcardItem_1, "vcard2" => vcardItem_2]
     * @param  array $vcardItems - vcardItems array
     * @return array $ newVcardItems - Updated vcardItems array
     */
    private function mergeOccurrences(array $occurencesArray, array $vcardItems): array
    {
        $finalData = [];
        foreach ($occurencesArray as $occurrences) 
        {
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
        
            $finalData = array_merge($finalData, $dataToMerge);
            
            $finalDataLength = count($finalData);            
            for ($i=0; $i < $finalDataLength; $i++)
            { 
                $entry = $finalData[$i];
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


            foreach ($finalData as $line)
            {
                $vcardItem->setData($line);
            }
            $vcardItems[] = $vcardItem;     
        }

        $newVcardItems = [];
        foreach($vcardItems as $vcardItem)    
        {
            if (!$vcardItem->getIsAnOccurrence()){ $newVcardItems[] = $vcardItem; }
        }
        // PHOTO
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
     * Sends the final Vcard file to user.
     *
     * @param  string $finalVcardContent
     * @return array
     */
    private function sendNewVcard(string $finalVcardContent): array
    {
        file_put_contents($this->tempPath.$this->finalVcardName.".vcf", $finalVcardContent);
        
        header('Content-Description: File Transfer');
        header('Content-Type: text/vcard');
        header("Content-Disposition: attachment; filename=\"$this->finalVcardName.vcf\"");
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($this->tempPath.$this->finalVcardName.".vcf"));

        readfile($this->tempPath.$this->finalVcardName.".vcf");
        unlink($this->tempPath.$this->finalVcardName.".vcf");

        return [];
    }
}
