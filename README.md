# # [PicoCMS](https://github.com/picocms/Pico) plugin : CSVGraph

Fetch data from csv files
Draw charts 
(Use [SVGGraph](https://github.com/goat1000/SVGGraph) to generate SVG charts on server side)

# Motivations

Adding charts in your Pico content.

# Install

Copy the `CSVGraphPlugin.php`  into the `plugins` folder.

# Use

```
[csv_graph file="/var/www/pico/data/user.csv" file="/var/www/pico/data/userIOS.csv" file="/var/www/pico/data/userAndroid    .csv" graph="MultiLineGraph" is_data_column="0"]```

```
[csv_graph file="/var/www/pico/data/users.csv"  graph="PieGraph" ]```
```

+ `file` : the filename of your csv file. Can add several filenames (for *MultiLineGraph* for instance)
+ `graph` : Choose any of the value in [grap type](https://www.goat1000.com/svggraph.php#graph-types). BarGraph, LineGraph, PieGraph, ...
+ `is_data_column` : boolean 0/1 (default : 1). Set if the data are in row or colum.
    + `is_data_column = "1"` : data are in columns and columns headers are used for x-axis. 
    
    |is_android|is_iphone|
    |----------|---------|
    |    5     |    2    |

    
    + `is_data_column = "0"` : data are in rows and the first column is used for x-axis.
    
    |   Month     |   Sale  |
    |-------------|---------|
    |    January  |    300  |
    |    February |    250  |
    |    March    |    123  |
    |    April    |    29   |
    
+ `width` & `height` (optional) : the width and the heigth of your chart (default : 640x480) 
+ `title` : the title of your chart
+ `settings` : you can add any of settings in *JSON style*. See [setting](https://www.goat1000.com/svggraph-settings.php#general-options). `settings="{'back_colour': 'white', 'graph_title': 'Start of Fibonacci series'}"` (use `` ` `` in this JSON settings instead of `"`)


