<?php

require 'svggraph/autoloader.php';



/**
 * CSVGraphPlugin
 * Extract rows from database query.
 * Insert field value into markdown content
 */
class CSVGraphPlugin extends AbstractPicoPlugin
{
   /**
	 * Stored config
	 */
	protected $config = array();
   
   /**
	 * Triggered after Pico has read its configuration
	 *
	 * @see    Pico::getConfig()
	 * @param  array &$config array of config variables
	 * @return void
	 */
	public function onConfigLoaded(array &$config)
	{
        if (isset($config['mysql_source']))
        {
            $db_conf = $config['mysql_source'];
            $i=0;
            foreach ($db_conf as $key => $value)
            {
                    foreach ($value as $key_param => $db_param)
                        $this->config[$key][$key_param] = $db_param;
                    $i++;
            }
        
        }
		
	}
	
    public function onContentPrepared(&$content)
    {
        $graphR = new Goat1000\SVGGraph\SVGGraph(640, 480);
//         $colours = array(
//   array('red', 'yellow'), array('blue', 'white'),array('red', 'white')
// );
       $graphR->colours(['red','green','blue']);
//        $graphR->colours($colours);
        // Search for Embed shortcodes allover the content
        preg_match_all('#\[csv_graph *.*?\]#s', $content, $matches);

        // Make sure we found some shortcodes
        if (count($matches[0]) > 0) {
            $error = false;
            // Walk through shortcodes one by one
            foreach ($matches[0] as $match) 
            {
                 // Get page content
                if ( ! preg_match_all('/file=[\"\']([^\"\']*)[\'\"]/', $match, $file))
                    $error = true;
                if ( ! preg_match('/graph=[\"\']([^\"\']*)[\'\"]/', $match, $graph))
                    $error = true;
               
                if (! $error)
                {
                    // Replace embeding code with the shortcode in the content
                    $result = $this->getData($file[1],$graph[1]);
                    $values = array(
 array('Dough' => 30, 'Ray' => 50, 'Me' => 40, 'So' => 25, 'Far' => 45, 'Lard' => 35),
 array('Dough' => 20, 'Ray' => 30, 'Me' => 20, 'So' => 15, 'Far' => 25, 'Lard' => 35,
  'Tea' => 45)
);
                   $graphR->values($result);
//                     $graphR->values($values);
                    $content = preg_replace('#\[csv_grap *.*?\]#s',  $graphR->fetch('MultiLineGraph', false), $content, 1);
                }
                else
                    $content = preg_replace('#\[csv_graph *.*?\]#s', '*CSVGraph ERROR*', $content, 1);
                
                $error = false;
                
            }
        }
        

       
    }
    
    
    private function getData($files, $line)
    {
        $results =array();
        $i = 0;
        foreach($files as $file)
        {
            if (($h = fopen($file, "r")) !== FALSE) 
            {
            // Convert each line into the local $data variable
                $file_data=array();
                while (($data = fgetcsv($h, 20, ",")) !== FALSE) 
                {		
                    $results[$i][$data[0]] = $data[1];   
                }

            // Close the file
            fclose($h);
            $i++;
            }
        }
        var_dump($results);
        return $results;
    }
       
}
