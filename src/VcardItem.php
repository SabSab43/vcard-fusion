<?php

namespace VcardFusion;

class VcardItem
{
    private $filename;
    private $version;
    private $fn;
    private $n;
    private $isAnOccurrence;
    private $data;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
        $this->isAnOccurrence = false;
    }

    /**
     * Get the value of filename
     */ 
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Set the value of filename
     *
     * @return  self
     */ 
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    
    /**
     * Get the value of version
     */ 
    public function getVersion()
    {
        return $this->version;
    }
    
    /**
     * Set the value of version
     *
     * @return  self
     */ 
    public function setVersion($version)
    {
            $this->version = $version;
        
            return $this;
        }
  
    /**
     * Get the value of data
     */ 
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the value of data
     *
     * @return  self
     */ 
    public function setData($data)
    {
        $this->data[] = $data;

        return $this;
    }

    /**
     * Get the value of fn
     */ 
    public function getFn()
    {
        return $this->fn;
    }

    /**
     * Set the value of fn
     *
     * @return  self
     */ 
    public function setFn($fn)
    {
        $this->fn = $fn;

        return $this;
    }

    /**
     * Get the value of n
     */ 
    public function getN()
    {
        return $this->n;
    }

    /**
     * Set the value of n
     *
     * @return  self
     */ 
    public function setN($n)
    {
        $this->n = $n;

        return $this;
    }

    /**
     * Get the value of isAnOccurrence
     */ 
    public function getIsAnOccurrence()
    {
        return $this->isAnOccurrence;
    }

    /**
     * Set the value of isAnOccurrence
     *
     * @return  self
     */ 
    public function setIsAnOccurrence($isAnOccurrence)
    {
        $this->isAnOccurrence = $isAnOccurrence;

        return $this;
    }
    
    /**
     *  Convert Data array into a formatted string
     *
     * @return string $data
     */
    public  function stringifyData(): string
    {
        $data = "";
        foreach ($this->data as $line) 
        {
            $data = $data.$line."\n";
        }
        return $data;
    }
}