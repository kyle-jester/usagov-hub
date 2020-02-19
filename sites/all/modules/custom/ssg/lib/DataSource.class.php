<?php

namespace ctac\ssg;

class DataSource
{
    use LoggingTrait;

	public $ssg;
	public $entities;
	public $redirects;
	public $freshData;
    public $updateData;
    public $dataPullTime;

	public function __construct( &$ssg )
	{
		$this->ssg          = $ssg;
		$this->entities     = [];
		$this->redirects    = [];
		$this->freshData    = false;
        $this->updateData   = true;
	}

	public function pull( $since=0 )
	{
        // JKH added check to the result
        if(!$this->getEntities($since)) {
        	return false;
        }
        $this->getRedirects();
        return true;
	}

	public function getEntities( $since=0 )
	{
		$this->entities     = [];
        return true;
	}

	public function getRedirects()
	{
        $this->redirects    = [];
        return true;
	}


    public function loadData()
    {
		$success = false;
		// JKH previous versions of this function would both load from source,
		// then update ... look at the old git code, and figure out how to do one or the other
		// and because the deletes are not working, i'm just going to load from source every time...
		$this->log("Data: loading fresh from source, every time ... ");
		if ( $this->loadDataFromSource() ) 
		{
			$this->log("done\n");
			$success = true;
		} 
		
		return $success;
    }
    
    public function updateDataFromSource()
    {
        $pulled = $this->pull($this->dataPullTime);
        if ( !$pulled )
        {
            return false;
        }
        return $this->storeDataInCache();
    }
    public function loadDataFromSource()
    {
        $pulled = $this->pull();
        if ( !$pulled )
        {
            return false;
        }
        return $this->storeDataInCache();
    }
    public function loadDataFromCache()
    {
        $cacheFile = $this->ssg->cacheDir.'/'.$this->ssg->config['siteName'].'.cache';
        if ( !file_exists($cacheFile) || !is_readable($cacheFile) ) { return false; }

        $lock = fopen($cacheFile, 'rb');
        @flock($lock, LOCK_SH);
        $cache = unserialize(file_get_contents($cacheFile));
        @flock($lock, LOCK_UN);
        fclose($lock);

        if ( empty($cache) || !array_key_exists('entities',$cache) ) { return false; }
        $this->dataPullTime = !empty($cache['time']) ? $cache['time'] : 0;
        $this->entities     = $cache['entities'];
        if ( array_key_exists('redirects', $cache) )
        {
            $this->redirects     = $cache['redirects'];
        }
        return true;
	}
	
	public function storeDataInCache()
    {
        $cacheFile = $this->ssg->cacheDir.'/'.$this->ssg->config['siteName'].'.cache';
        if ( !file_exists($cacheFile) )
        {
            touch($cacheFile);
        }
        if ( !is_writable($cacheFile) ) 
        { 
            chmod($cacheFile,0644);
        }
        $cache = serialize([
            'time'         => $this->dataPullTime,
            'entities'     => $this->entities, 
            'redirects'    => $this->redirects
        ]);
        $bytes = file_put_contents($cacheFile, $cache, LOCK_EX);
        return !empty( $bytes );
    }
}
