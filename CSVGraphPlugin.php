<?php

require 'svggraph/autoloader.php';



/**
 * CSVGraphPlugin
 * Extract rows from database query.
 * Insert field value into markdown content
 */
class CSVGraphPlugin extends AbstractPicoPlugin
{
   
    public function onContentPrepared(&$content)
    {
 
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
                preg_match('/title=[\"]([^\"]*)[\"]/', $match, $title); 
                preg_match('/height=[\"]([^\"]*)[\"]/', $match, $height);
                preg_match('/width=[\"]([^\"]*)[\"]/', $match, $width);
                preg_match('/settings=[\"]([^\"]*)[\"]/', $match, $settings_conf);
                preg_match('/is_data_column=[\"]([^\"]*)[\"]/', $match, $column);
                if (! $error)
                {
                    if($column != null)
                            $is_column = $column[1];
                        else
                            $is_column = 1;
                            
                    // Replace embeding code with the shortcode in the content
                    $result = $this->getData($file[1],$graph[1], $is_column);
                    $settings = array();
                    if ($title != null)
                        $settings['graph_title']=$title[1];
                    if ($settings_conf != null)
                    {
                        $settings = json_decode(str_replace('\'','"',$settings_conf[1]),true);
                    }
                    if($width != null && $height != null)
                        $graphR = new Goat1000\SVGGraph\SVGGraph($width[1], $height[1],$settings);
                    else
                        $graphR = new Goat1000\SVGGraph\SVGGraph(640, 480,$settings);
                    $graphR->values($result);
                    
                    $content = preg_replace('#\[csv_grap *.*?\]#s',  $graphR->fetch($graph[1], false), $content, 1);
                }
                else
                    $content = preg_replace('#\[csv_graph *.*?\]#s', '*CSVGraph ERROR*', $content, 1);
                
                $error = false;
                
            }
        }
        
    }
    
    
    private function getData($files, $line,$is_column)
    {
        $results =array();
        $i = 0;
        foreach($files as $file)
        {
            if (($h = fopen($file, "r")) !== FALSE) 
            {
            // Convert each line into the local $data variable
                if($is_column)
                {
                      
                    $header = fgetcsv($h, 20, ",");
                    while (($data = fgetcsv($h, 20, ",")) !== FALSE) 
                    {	
                        $j=0;
                        foreach($header as $field)
                        {
                            $results[$i][$field] = $data[$j];   
                            $j++;
                        }
                    }   
                    
                }
                else
                {
                    while (($data = fgetcsv($h, 20, ",")) !== FALSE) 
                    {		
                        $results[$i][$data[0]] = $data[1];   
                    }    
                        
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
