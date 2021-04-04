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

    public function __construct(array $files = [], string $version, bool $checkVersion, bool $checkOccurrences)
    {
        $this->files = $files['files'];
        $this->nbFiles = count($files['files']['name']);
        $this->tempPath =  dirname(__DIR__).DIRECTORY_SEPARATOR."tmp".DIRECTORY_SEPARATOR;
        $this->finalVcardName = uniqid().'-vcard';
        $this->version = $version;
        $this->checkVersion = $checkVersion;
        $this->checkOccurrences = $checkOccurrences;
    }   
        
    /**
     * Checks data sends by User, return an empty array error if all files are goods.
     *
     * @return array
     */
    public function checkRequest(): array
    {
        $errors = [];
        for($i=0; $i < $this->nbFiles; $i++) 
        { 
            if ($this->files['name'][$i] == "") {
                $errors[] = "Aucun fichier n'a été envoyé."; 
                return $errors;
            }

            if ($this->files['size'][$i] <= 0) { 
                $errors[] = "Le fichier \"".$this->files['name'][$i]."\" est vide."; 
            }             

            if (!substr($this->files['name'][$i], -4) === ".vcf" || !substr($this->files['name'][$i], -6) === ".vcard") 
            { 
                $errors[] = "Le fichier \"".$this->files['name'][$i]."\" doit être au format .vcf ou .vcard."; 
            }
        }
        return $errors;
    }

    /**
     * Merges all Vcard Files into one Vcard file,it has checking occurrences and checking Vcards Versions options
     *
     * @return void
     */
    public function mergeVcardFiles( bool $checkVersions = false, bool $checkOccurrences = false): array
    {
        $vcardsArray = $this->convertFilesToArray();
        $vcardItems = $this->vcardsItemsGenerator($vcardsArray);
        $errors = [];

        if ($this->checkVersion){ $errors = $this->checkVersion($vcardItems); }

        if (!empty($errors)){ return $errors; }

        if ($this->checkOccurrences){ 
            /** @var array */
            $occurrencesArray = $this->searchOccurrences($vcardItems); 

            if (!empty($occurrencesArray)) {
                $this->mergeOccurrences($occurrencesArray);
            }
            
        }

        $this->setFinalContentVcard($vcardItems);

        $finalVcardContent = "";
        /** @var VcardItem */
        foreach ($vcardItems as $vcardItem) 
        {
            $vcardData = $vcardItem->getData(); 
            foreach ( $vcardData as $line) 
            {
                $finalVcardContent = $finalVcardContent.$line."\n";
            }
        }
        if (empty($errors)) 
        {
            $errors = $this->sendNewVcard($finalVcardContent);
        }

        return $errors;
    }

    /**
     * Checks if all Vcards have same version, return an error if they don't have same version.
     *
     * @return string||null
     */
    private function checkVersion(array $vcardsItems): array
    {
        $errors = [];
        /** @var VcardItem*/
        foreach ($vcardsItems as $vcardItem) {
            switch ($this->version) {
                case $this->version != $vcardItem->getVersion():
                    $errors[] = "la Vcard \"".$vcardItem->getFilename()."\" est au format Vcard".$vcardItem->getVersion().". Format attendu:  Vcard$this->version.";
                    break;
            }
        }
        return $errors;
    }

    /**
     * Return one array per file.
     *
     * @return array
     */
    private function convertFilesToArray(): array
    {
        $vcardsArray = [];
        for ($i=0; $i < $this->nbFiles; $i++) 
        { 
            $vcardArray = ["filename" => $this->files['name'][$i], "data" => file($this->files['tmp_name'][$i], FILE_IGNORE_NEW_LINES)];
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
        foreach ($vcardsArray as $vcardArray) 
        {     
            foreach ($vcardArray["data"] as $vcardLine) 
            { 
                if (!isset($vcardItem)) {
                    $vcardItem = new VcardItem($vcardArray["filename"]);
                }
                switch ($vcardLine)
                {
                    case $vcardLine === "BEGIN:VCARD":
                        $vcardItem->setData($vcardLine);
                        break;

                    case substr($vcardLine, 0, 8) === "VERSION:":
                        $vcardItem->setVersion(substr($vcardLine, -3));
                        $vcardItem->setData($vcardLine);
                        break;

                    case substr($vcardLine, 0, 3) === "FN:":
                        $vcardItem->setFn($vcardLine);
                        $vcardItem->setData($vcardLine);
                        break;
                        
                    case substr($vcardLine, 0, 2) === "N:":
                        $vcardItem->setN($vcardLine);
                        $vcardItem->setData($vcardLine);
                        break;

                    case $vcardLine === "END:VCARD":
                        $vcardItem->setData($vcardLine);
                        $vcardItems[] = $vcardItem;
                        unset($vcardItem);
                        break;

                    default:
                        $vcardItem->setData($vcardLine);
                        break;
                }
            }
        }
        return $vcardItems;
    }
    
    /**
     * search all occurrences, need versions of vcard files.
     *
     * @param  array $vcardItems
     * @return void
     */
    private function searchOccurrences(array $vcardItems)
    {
        switch ($this->version) {
            case '2.1':
                break;
            
            case '3.0' || '4.0':
                break;               
        }

        $vcardItemsLength = count($vcardItems);
        $occurencesArray = [];
        for ($i=0; $i < $vcardItemsLength; $i++)
        {             
            if (!$vcardItems[$i]->getIsAnOccurrence())
            {
                for ($j=1; $j < $vcardItemsLength-1; $j++) 
                {
                    if ($vcardItems[$i]->getFn() === $vcardItems[$j]->getFn())
                    {
                        $occurencesArray[] = ["vcard1" => $vcardItems[$i], "vcard2" => $vcardItems[$j]];
                        $vcardItems[$i]->setIsAnOccurrence(true);
                        $vcardItems[$j]->setIsAnOccurrence(true);
                    }
                }
            }
        }
        return $occurencesArray;
    }
    
    /**
     * Return all "N" vcard properties
     *
     * @param  array $vcardItems
     * @return array $vcardsNames
     */
    private function getAllVcardItesmN(array $vcardItems): array
    {
        /** @var VcardItem */
        foreach ($vcardItems as $vcardItem) {
            $vcardNames[] = $vcardItem->getN();
        }
        return $vcardNames;
    }
    
    /**
     * Return all "FN" vcard properties
     *
     * @param  array $vcardItems
     * @return array $vcardsNamesy
     */
    private function getAllVcardItemsFn(array $vcardItems): array
    {
        /** @var VcardItem */
        foreach ($vcardItems as $vcardItem) {
            $vcardNames[] = $vcardItem->getFn();
        }
        return $vcardNames;
    }
    
    private function mergeOccurrences(array $occurencesArray)
    {
        var_dump($occurencesArray); exit;
    }

    private function setFinalContentVcard(array $vcardItems)
    {

    }



    /**
     * Sends the final Vcard file to user and return a message statut.
     *
     * @param  string $finalVcardContent
     * @return string
     */
    private function sendNewVcard(string $finalVcardContent): array
    {
        // file_put_contents($this->tempPath.$this->finalVcardName.".vcf", $finalVcardContent);

        // header('Content-Description: File Transfer');
        // header('Content-Type: text/vcard');
        // header("Content-Disposition: attachment; filename=\"$this->finalVcardName.vcf\"");
        // header('Expires: 0');
        // header('Cache-Control: must-revalidate');
        // header('Pragma: public');
        // header('Content-Length: ' . filesize($this->tempPath.$this->finalVcardName.".vcf"));
        
        // readfile($this->tempPath.$this->finalVcardName.".vcf");
        // unlink($this->tempPath.$this->finalVcardName.".vcf");

        return [];
    }
}
