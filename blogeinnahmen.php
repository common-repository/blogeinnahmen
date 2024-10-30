<?php

/*
Plugin Name: Blogeinnahmen
Plugin URI: http://www.allblogs.de
Description:  verwaltet die Blogeinnahmen im Adminbereich
Version: 1.2
Author: Timon Kretzschmar, Maximilian Wenzel
Author URI: http://www.allblogs.de/wordpress-plugins/
*/

register_activation_hook(__FILE__,'create_datatable');
add_action('admin_menu','add_admin_page');
add_action('init','add_admin_head');
add_action('wp_ajax_delete_col', 'delete_col');
add_action('wp_ajax_delete_row', 'delete_row');
add_shortcode('blogeinnahmen_bar', 'bar_chart');
add_shortcode('blogeinnahmen_pie', 'pie_chart');

if ( !defined( 'WP_ADMIN_URL' ) )
      {  define( 'WP_ADMIN_URL', get_option( 'siteurl' ) . '/wp-admin' );
      }

define('first_dynamic_column', 6);
 
$plugin = plugin_basename(__FILE__); 
add_filter("plugin_action_links_$plugin", 'plugin_settings_link' );  

function add_admin_page()
  {
    global $wpdb;
    if(isset($_GET['action']))
    {
      if($_GET['action'] == 'download')
      {
        header("Content-type: text/csv");  
        header("Cache-Control: no-store, no-cache");  
        header('Content-Disposition: attachment; filename="blogeinnahmen-'.date('Y-m-d_H-i').'.csv"');
      
        $result = mysql_query("Select * FROM ".$wpdb->prefix."blogeinnahmen ORDER BY month") or Die (mysql_error());
  			while ($row = mysql_fetch_assoc($result))
  				{
  					$stats[] = $row;
  				}
  		  $result = mysql_query("SHOW COLUMNS FROM ".$wpdb->prefix."blogeinnahmen ") or Die ('002');
  			while ($row = mysql_fetch_assoc($result))
  				{
  					$columns[] = $row;
  				}
      
        $outstream = fopen("php://output", 'w');
  
        $tmp = array('Monat','Einnahmen','eCPM','Besucher','PIs');
        for($i = first_dynamic_column; $i < count($columns);$i++)
        {
          $tmp[] = $columns[$i]['Field'];
        }
        fputcsv($outstream, $tmp,';');
          
          foreach ($stats as $fields)
            {
              $tmp = array();
              foreach($fields as $key => $value)
                if($key != 'id')
                $tmp[] = $value;
              fputcsv($outstream, $tmp,';');
            }
          fclose($outstream);
          die();
      }
    }
    add_menu_page('Blogeinnahmen','Blogeinnahmen',10,__FILE__,'optionsmenu');
    add_action('admin_init','add_admin_head');
    add_action('delete_col', 'delete_col');
  }
 
function add_admin_head()
{   
    $pluginfolder = get_bloginfo('url') . '/' . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
  	wp_enqueue_script('jquery');
  	wp_enqueue_script('jquery-ui-core');
  	wp_enqueue_script('ui.datepicker.min', $pluginfolder . '/ui.datepicker.min.js', array('jquery', 'jquery-ui-core') );
  	wp_enqueue_style('jquery.ui.theme', $pluginfolder  . '/js/ui-lightness/jquery-ui-1.7.3.custom.css');
  	wp_enqueue_style('style', $pluginfolder . '/style.css');
    wp_enqueue_script('flot', $pluginfolder . '/flot/jquery.flot.js');
  	wp_enqueue_script('pie', $pluginfolder . '/flot/jquery.flot.pie.js');
} 

function plugin_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=blogeinnahmen/blogeinnahmen.php">Einstellungen</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 
function optionsmenu()
  {
    global $wpdb;   
    $error = 0;

    $trans = array(
          'January'   => 'Januar',
          'February'  => 'Februar',
          'March'     => 'M&auml;rz',
          'May'       => 'Mai',
          'June'      => 'Juni',
          'July'      => 'Juli',
          'October'   => 'Oktober',
          'December'  => 'Dezember'
      );
    $stats = array();
    $columns = array();
    $result = mysql_query("Select * FROM ".$wpdb->prefix."blogeinnahmen ORDER BY month") or Die (mysql_error());
			while ($row = mysql_fetch_assoc($result))
				{
					$stats[] = $row;
				}
		$result = mysql_query("SHOW COLUMNS FROM ".$wpdb->prefix."blogeinnahmen ") or Die ('002');
			while ($row = mysql_fetch_assoc($result))
				{
					$columns[] = $row;
				}		
    //print_r($stats );
    //print_r($columns);
    //print_r($_POST);
    
    
    
    if (isset($_POST['id']))
      {
        // neue Spalten zur Datenbanktabelle hinzufügen
        $num = 0;
        $error = 0;
        
        while (array_key_exists('advert'.$num, $_POST))
          {
             $_POST['advert'.$num] = str_replace(' ', '_',$_POST['advert'.$num]);
             if (is_numeric($_POST['advert'.$num]))
             {
                $_POST['advert'.$num] = (string)$_POST['advert'.$num];
             }
             
             $result = mysql_query("Select * FROM ".$wpdb->prefix."blogeinnahmen ORDER BY month") or Die (mysql_error());
        			while ($row = mysql_fetch_assoc($result))
        				{
        					$stats[] = $row;
        				}
         
             if(!array_key_exists($_POST['advert'.$num], $stats))
             {
                update_table($_POST['advert'.$num]);
             }          
             $num++;
          }
              
        foreach ($_POST as $key => $value)
          {
              if(substr($key,0,3) == 'col')
                {
                  $post_keys[] = $key;
                }
          }
          
          //prüfen ob ID schon in Datenbank vorhanden
                // --> UPDATE oder INSERT         
        for ($i = 0; $i < count($_POST['id']); $i++)          //durchzählen der Zeilen aus der Tabelle
          {
              if (isset($stats[$i]['id']))
                {
                   //Update // dafür wird der Query Stück für Stück zusammengebaut
                   $query = "";
                   $query = "UPDATE ".$wpdb->prefix."blogeinnahmen SET ";
                   for ($k = 1; $k < count($columns); $k++)        //durchzählen der Spalten der Datenbanktabelle
                    {
                      //es muss geprüft werden, ob für die Spalte Monat ein verwertbares Datum eingetrage wurde, um es in die Datenbank zu schreiben
                      if($columns[$k]['Field'] == 'month')
                        {
                          if($_POST['selected_date'][$i]!= '') 
                            { 
                              if(strtotime($_POST['selected_date'][$i]))
                              {
                                $query .= $columns[$k]['Field']."='".strtotime($_POST['selected_date'][$i])."', ";
                              }
                            }
                            else
                            {
                              break;
                            }
                        }
                      //es muss geprüft werden, ob die Werte für Besucher und PIs ganzzahlig und numerisch sind
                      elseif ($columns[$k]['Field'] == 'visitors' OR $columns[$k]['Field'] == 'clicks')
                        {
                           $_POST[$post_keys[$k-1]][$i] = str_replace(',','.',$_POST[$post_keys[$k-1]][$i]);
                           $value1 = round($_POST[$post_keys[$k-1]][$i]);
                           if (is_numeric($value1) AND $value1 == $_POST[$post_keys[$k-1]][$i] AND $value1 > 0)
                            {
                              $query .=  $columns[$k]['Field']."='".mysql_real_escape_string($_POST[$post_keys[$k-1]][$i])."', ";
                            }
                            elseif (is_numeric($_POST[$post_keys[$k-1]][$i]) AND $_POST[$post_keys[$k-1]][$i] == 0)
                              {
                                if($columns[$k]['Field'] == 'visitors')
                                {
                                  $query .=  $columns[$k]['Field']."='".mysql_real_escape_string($_POST[$post_keys[$k-1]][$i])."', ";  
                                }
                                else
                                  {
                                    $error = 4;
                                    $error_date = $_POST['column1'][$i];
                                  }  
                              }
                              else
                                {
                                  $error_date = $_POST['column1'][$i];
                                  $error = 2;
                                }
                        }                        
                        else
                          {       
                            //für die restlichen Spalten muss nur geprüft werden, ob der Wert numerisch ist
                            if (is_numeric($_POST[$post_keys[$k-1]][$i]))
                              {
                                if($columns[$k]['Field'] != "")
                                {
                                  $query .=  $columns[$k]['Field']."='".mysql_real_escape_string($_POST[$post_keys[$k-1]][$i])."', ";
                                }
                                else
                                  {
                                    $error = 3;
                                  }
                              }
                            elseif($_POST[$post_keys[$k-1]][$i] == '')
                              {
                                $query .=  $columns[$k]['Field']."='0.00', "; 
                              }
                              else
                                {
                                  $error_date = $_POST['column1'][$i];
                                  $error = 1;                                    
                                }
                              
                          }
                      $col_num = $k;
                    }
                  //nun werden die Daten aus den neu hinzugefügten Spalten in die Datenbank geschrieben. Die Spalte selbst wurde schon weiter oben mit update_table() erstellt. 
                  for ($k =0; $k < $num; $k++)
                    {
                      $col_num++;
                      if ($_POST[$post_keys[$col_num-1]][$i] != '' )
                      {                        
                        if ($_POST['advert'.$k] == '')
                        {
                          $error = 3;
                        }
                        else
                        {
                          if (is_numeric($_POST[$post_keys[$col_num-1]][$i]))
                            {                            
                              $query .=  mysql_real_escape_string($_POST['advert'.$k])."='".mysql_real_escape_string($_POST[$post_keys[$col_num-1]][$i])."', ";
                            }
                            else
                              {
                                $error_date = $_POST['column1'][$i];
                                $error = 1;
                              } 
                        }
                      }
                      elseif($_POST['advert'.$k] == '')
                        {
                            
                            //wenn die neue Spalte nicht beschriftet wurde, wird nichts in die Datenbank geschrieben
                        } 
                      else
                        {
                          //wenn kein Wert eingetragen wurde, wird automatisch 0.00 gespeichert
                          $query .=  mysql_real_escape_string($_POST['advert'.$k])."='0.00', ";
                        }  
                    }
                  $query = substr($query, 0, -2);
                  $query .= " WHERE id='".mysql_real_escape_string($_POST['id'][$i])."';";
                  
                  mysql_query($query) OR Die (mysql_error());
                }  
                else
                  {
                    //Insert der neuen Zeilen , analog zum Update oben
                    $query = '';
                    if ($_POST['selected_date'][$i]!= '')
                    {
                      $query = "INSERT INTO ".$wpdb->prefix."blogeinnahmen SET ";
                      $col_num = 0;
                      for ($k = 1; $k < count($columns);$k++)
                        {
                          if($columns[$k]['Field'] == 'month')                    
                            {
                                  if(strtotime($_POST['selected_date'][$i]))
                                    {
                                      $query .= $columns[$k]['Field']."='".strtotime($_POST['selected_date'][$i])."', ";
                                    }                                
                            }
                          elseif ($columns[$k]['Field'] == 'visitors' OR $columns[$k]['Field'] == 'clicks')
                            {
                               $_POST['column'.$k][$i] = str_replace(',','.',$_POST['column'.$k][$i]);
                               $value2 = round($_POST['column'.$k][$i]);
                               if (is_numeric($value2) AND $value2 == $_POST['column'.$k][$i] AND $value2 > 0)
                                {
                                  $query .=  $columns[$k]['Field']."='".mysql_real_escape_string($_POST['column'.$k][$i])."', "; 
                                }
                                elseif (strlen(trim($_POST['column'.$k][$i])) == 0)
                                  {
                                    if($columns[$k]['Field'] == 'visitors')
                                    {
                                      $query .=  $columns[$k]['Field']."='".mysql_real_escape_string($_POST['column'.$k][$i])."', ";  
                                    }
                                    else
                                      {
                                        $error = 4;
                                        $error_date = $_POST['column1'][$i];
                                      }
                                  }
                                  else
                                    {
                                      $error_date =  $_POST['column1'][$i];
                                      $error = 2;
                                    }
                            }
                            else
                              {
                                  if ($_POST['column'.$k][$i] == '')
                                    {
                                      $query .=  $columns[$k]['Field']."='0.00', "; 
                                    }
                                  elseif (is_numeric($_POST['column'.$k][$i]))
                                    {
                                      $query .=  $columns[$k]['Field']."='".mysql_real_escape_string($_POST['column'.$k][$i])."', ";
                                    }
                                    else
                                      {
                                        $error_date = $_POST['column1'][$i];
                                        $error = 1;
                                      }
                              } 
                          $col_num = $k;
                        }
                      for ($k =0; $k < $num; $k++)
                        {
                          $col_num++;
                          if ($_POST['column'.$col_num][$i] != '')
                            {
                              if(is_numeric($_POST['column'.$col_num][$i]) )
                                {
                                  $query .=  mysql_real_escape_string($_POST['advert'.$k])."='".mysql_real_escape_string($_POST['column'.$col_num][$i])."', ";
                                }
                                else
                                  {
                                    $error_date = $_POST['column1'][$i];
                                    $error = 1;
                                  }
                            }
                          elseif($_POST['advert'.$k] == '')
                            {}  
                          else
                            {
                              $query .=  mysql_real_escape_string($_POST['advert'.$k])."='0.00', ";
                            }
                        }
                      $query = substr($query, 0, -2);
                      $query .= ";";
                      
                      
                      mysql_query($query) OR Die (mysql_error());
                    }  
                  }
          }
          
        
        //die Arrays $stats und $columns werden auf den neuesten Stand gebracht
        $stats = array();
        $result = mysql_query("SELECT * FROM ".$wpdb->prefix."blogeinnahmen ORDER BY month") or Die ('003');                                 
  			 while ($row = mysql_fetch_assoc($result))
  				{
  					$stats[] = $row;
  				}
        $columns = array();
        $result = mysql_query("SHOW COLUMNS FROM ".$wpdb->prefix."blogeinnahmen ") or Die ('002');
    			while ($row = mysql_fetch_assoc($result))
    				{
    					$columns[] = $row;
    				}                                        
      }
     
     
    if (isset($_POST['category1']))
      {
          //Balkendiagramm
          bar_chart(array('cat1'=>$_POST['category1'], 'cat2'=>$_POST['category2'], 'time'=>$_POST['time'], 'currenttime'=> time() ));    
      }
      
      
    if(isset($_POST['pie_month']))
      {
          //Kreisdiagramm
          pie_chart(array('month'=>$_POST['pie_month'], 'currenttime'=> time()));   
      } 
         	     			
  ?>
  <!--Aufbau der Admin-Seite-->
  <div>
    <h2> Blogeinnahmen verwalten </h2>
    <div style="width:100%; text-align:right;"> <a href="<? echo $_SERVER['REQUEST_URI'].'&action=download'; ?>" class="button"> Export als csv</a></div>
    <form action="" method="POST">
      <table id="stats">
        <tr>
          <th> <input name="id" type="hidden" size="1" maxlength="40" > </th>
          <?  
              /////// Tabellenkopf //////////
              $keys = array('id', 'Monat', 'Einnahmen', 'eCPM', 'Besucher', 'PIs' );    //feste, unveränderbare Spalten
              
              for ($i = first_dynamic_column; $i < count($columns); $i++)
                {
                  //die vom User hinzugefügten Spalten werden aus der Datenbank geholt und mit einem Link zum Löschen der Spalte versehen
                  $keys[] = $columns[$i]['Field'].'  <a href="javascript:deleteColumn(\'stats\','.$i.',\''.WP_ADMIN_URL.'\');">x</a>';                       
                }
                
              for ($i = 1; $i < count($keys); $i++)
                {                  
                   echo '<th> '. $keys[$i]. '</th>';  
                }
                
          ?>
         
          <th> <a href="javascript:addColumnToTable('stats');"> neue Spalte</a>  </th>
        </tr>  
        
        <? 
          for ($l = 0; $l < count($columns);$l++)
              {
                $stats_keys[] = $columns[$l]['Field'];    //alle Spalten, so wie sie in der Datenbank bezeichnet sind (im Unterschied zu $keys)
              }
              
          if (isset($stats[0]))
          { 
            /////// Tabelle erstellen ////////// 
            //alle Zellen erhalten eine ID nach dem Muster "Spalte_Zeile". Außerdem erhält jede Reihe eine ID "row(Nummer)".
            setlocale (LC_ALL, 'de_DE');
            for ($i = 0; $i < count($stats); $i++)
              { 
                if($error_date != strtr(date('F Y',$stats[$i]['month']+7200),$trans))
                {                                                                                                                     
                  echo '<tr id="row'.$i.'">';
                }
                else
                  {
                    echo '<tr id="row'.$i.'" bgcolor="#CD3333">';
                  }
                echo '<td> <input name="id[]" id="0_'.$i.'" type="hidden" size="2" maxlength="15" value="'.$stats[$i]['id'].'"> </td>';                                                                                                     
                echo '<td> <input name="column1[]" id="1_'.$i.'" type="text" size="15" maxlength="15" value="'.strtr(date('F Y',$stats[$i]['month']+7200),$trans).'"> <input type="hidden" id="hidden_date_'.$i.'" name="selected_date[]" value="'.date('D, d M Y H:i:s O',$stats[$i]['month']).'"> <a href="#" onclick="javascript:deleteRow(\'stats\',this.parentNode.parentNode.rowIndex,\''.WP_ADMIN_URL.'\');">x</a> </td>';
                echo '<td> <input name="column2[]" id="2_'.$i.'" type="text" size="7" maxlength="15" value="'.$stats[$i]['total'].'" readonly> </td>';
                echo '<td> <input name="column3[]" id="3_'.$i.'" type="text" size="7" maxlength="15" value="'.$stats[$i]['eCPM'].'" readonly> </td>';
                for ($k = 4; $k < count($stats[0]); $k++)
                  { 
                    ?>                  
                    <td> <input name="column<? echo $k; ?>[]" id="<? echo $k.'_'.$i; ?>" type="text" size="7" maxlength="15" onChange="javascript:calc_total('stats',this.parentNode.parentNode.rowIndex,0);" value="<? echo $stats[$i][$stats_keys[$k]]; ?>"> </td>
               <? }
               
                echo '</tr>';  
              }
          }
          else
            {
              /////// Tabelle ohne Inhalt (1.Start) //////////
              echo '<tr id="row0">';
              echo '<td> <input name="id[]" type="hidden" size="2" maxlength="15" > </td>';
              echo '<td> <input name="column1[]" id="1_0" type="text" size="15" maxlength="15" > <input type="hidden" id="hidden_date_0" name="selected_date[]" > </td>';
              echo '<td> <input name="column2[]" id="2_0" type="text" size="7" maxlength="15"  readonly> </td>';
              echo '<td> <input name="column3[]" id="3_0" type="text" size="7" maxlength="15"  readonly> </td>';
              
              for ($i = 4; $i < count($stats_keys);$i++)
                {
                  ?>
                  <td> <input name="column<? echo $i; ?>[]" id="<? echo $i; ?>_0" type="text" size="7" maxlength="15" onChange="javascript:calc_total('stats',0,0);" > </td>
                  <?
                }
              echo '</tr>';  
            }
            
        ?>
                         
      </table> 
      <?
        if ($error == 1)
            {
            
              echo '<font size="+1" color="#FF0000">Fehlermeldung: </font><b> Es ist ein Fehler aufgetreten, weil ein Wert nicht numerisch ist. </b><br>'; 
            }
          if ($error == 2)
            {
              echo '<font size="+1" color="#FF0000">Fehlermeldung: </font><b> Es ist ein Fehler aufgetreten, weil die Werte f&uuml;r Besucher und PI ganzzahlig sein m&uuml;ssen. </b><br>';
            }
          if ($error == 3)
            {
              echo '<font size="+1" color="#FF0000">Fehlermeldung: </font><b> Es ist ein Fehler aufgetreten, weil eine Spalte nicht benannt wurde. </b>';
              echo '<a href="javascript:history.back()">  Eingabe wiederholen!  </a><br>';
            }
          if($error == 4)
            {
              echo '<font size="+1" color="#FF0000">Fehlermeldung: </font><b> Es ist ein Fehler aufgetreten, weil der Wert f&uuml;r PIs nicht Null sein darf. </b><br>';
            }
      ?>
      <a href="javascript:addRowToTable('stats','<? echo implode('-',$stats_keys);?>');"> + </a> <br>    
      <input type="submit" class="button" value=" Absenden "><p>
       
    </form>
    
    
    <h2> Balkendiagramm </h2>
    
    <form action="" method="POST">  
      1. Kategorie: 
      <select name="category1" size="1">
        <? 
          echo '<option></option>';
          echo '<option value="total" > Einnahmen </option>';
          echo '<option value="eCPM"> eCPM </option>';
          echo '<option value="visitors"> Besucher </option>';
          echo '<option value="clicks"> PIs </option>';
          for ($i = first_dynamic_column; $i < count($columns); $i++)
              {
                echo '<option> '.$columns[$i]['Field'].' </option>';  
              } 
        ?>
      </select><p>
      2. Kategorie: 
      <select name="category2" size="1">
        <? 
          echo '<option></option>';
          echo '<option value="total"> Einnahmen </option>';
          echo '<option value="eCPM"> eCPM </option>';
          echo '<option value="visitors"> Besucher </option>';
          echo '<option value="clicks"> PIs </option>';
          for ($i = first_dynamic_column ; $i < count($columns); $i++)
              {
                echo '<option> '.$columns[$i]['Field'].' </option>';  
              } 
        ?>
      </select><p>
      Zeitraum:
      <select name="time" size="1">
        <option value="3"> letzten 3 Monate </option>
        <option value="6"> letzten 6 Monate </option>
        <option value="12"> letzten 12 Monate </option>
      </select>  
      <p><input type="submit" class="button" value="Anzeigen"><p>
      <!-- die folgenden divs dienen als Platzhalter-->
      <?
      if (isset($_POST['time']))
        {
          echo '<div id="shortcode_bar"></div><p>      
                <div id="label_container" style="width:100px;"></div>                 
                <div id="placeholder" class="barchart" ></div>';                                        
        }
      ?>
     </form><br><br>  
     
     
     <h2>Kreisdiagramm</h2>
     
     <form action="" method="POST">
     <select name="pie_month" size="1">
        <?
          for ($i = 0; $i < count($stats); $i++)
            {
              echo "<option value='".$stats[$i]['month']."'> ".strtr(date('F Y',$stats[$i]['month']+7200),$trans)."</option>";
            }                                                                                      
        echo "<option value='3'> letzten 3 Monate </option>";
        echo "<option value='6'> letzten 6 Monate </option>";
        echo "<option value='12'> letzten 12 Monate </option>";
        ?>   
     </select>
     <p><input type="submit" class="button" value="Anzeigen"><p>
     <!-- divs als Platzhalter -->
     <?
     if (isset($_POST['pie_month']))
      {
         echo ' <div id="shortcode_pie"></div>
                <div id="pie_place" class="piechart"></div> ';
      }   
     ?>    
    
    
    <script type="text/javascript">
      
       /////// Kalender definieren //////////
       jQuery(document).ready(function(){ 
           <?
           //Initialisierung des Kalenders (für den 1. Start)
          echo  "
                jQuery('#1_'+0).datepicker({
                  changeMonth: true,
                  changeYear: true,
                  showButtonPanel: true,
                  dateFormat: 'MM yy',
                  monthNames: ['Januar', 'Februar', '".utf8_encode('März')."', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
                  monthNamesShort: ['Jan', 'Feb', 'M&auml;r', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
                  onClose: function(dateText, inst) { 
                      var month = jQuery('#be-ui-datepicker-div .be-ui-datepicker-month :selected').val();
                      var year = jQuery('#be-ui-datepicker-div .be-ui-datepicker-year :selected').val();
                      var date_ini = new Date(year,month, 1);
                      jQuery(this).datepicker('setDate', date_ini);
                      jQuery('#hidden_date_'+0).val(date_ini.toGMTString());        //speichern des timestamps für die Datenbank
                      }
      		});  ";
      		  //da nur die Monate entscheidend sind, wird die Anzeige der Kalendertage ausgeblendet
      		  echo "
               jQuery('#1_'+0).focus(function () {
                  jQuery('.be-ui-datepicker-calendar').hide();
                  });  "; 
          
          //Initialisierung für den Fall, dass die Tabelle bereits Daten enthält        
          for ($i = 0; $i < count($stats); $i++)
      		   {
      		   echo  "
                jQuery('#1_".$i."').datepicker({
                  changeMonth: true,
                  changeYear: true,
                  showButtonPanel: true,
                  dateFormat: 'MM yy',
                  monthNames: ['Januar', 'Februar', '".utf8_encode('März')."', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
                  monthNamesShort: ['Jan', 'Feb', 'M&auml;r', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
                  onClose: function(dateText, inst) { 
                      var month = jQuery('#be-ui-datepicker-div .be-ui-datepicker-month :selected').val();
                      var year = jQuery('#be-ui-datepicker-div .be-ui-datepicker-year :selected').val();
                      var date_ini = new Date(year,month, 1);
                      jQuery(this).datepicker('setDate', date_ini);
                      jQuery('#hidden_date_".$i."').val(date_ini.toGMTString());
                      }
      		});  ";
      		
      		  echo "
               jQuery('#1_".$i."').focus(function () {
                  jQuery('.be-ui-datepicker-calendar').hide();
                  });  ";
                
            } 
      		?>
      		        
         });       
      </script>
    </form>         
  </div>
  
  <script language="JavaScript" type="text/javascript" charset="utf-8">
    var k = 0;
    
    /////// Tabellenspalte hinzufügen //////////
    function addColumnToTable(id_name)
      {
         var i = 0;
         var tbl = document.getElementById(id_name);       

         for ( i = 0; i < tbl.rows.length; i++)
          {
            if (i == 0)
              {
                /////// Zelle Spaltenname //////////
                var cellbox = tbl.rows[i].insertCell(tbl.rows[i].cells.length-1);
                var box = document.createElement('input');
                box.setAttribute('type', 'text');
                box.setAttribute('name', 'advert'+k);
                box.setAttribute('id', 'advert'+k);
                box.setAttribute('size', '10');
                box.setAttribute('maxlength', '40');
                box.setAttribute('onChange', "javascript:numeric("+k+");");
                cellbox.appendChild(box);
                var link = document.createElement('a');
                link.setAttribute('href',"javascript:deleteColumn('stats',"+((tbl.rows[0].cells.length)-2)+",'<? echo WP_ADMIN_URL; ?>');");
                link.innerHTML = 'x';
                cellbox.appendChild(link);
              }
              else
                {
                    /////// restliche Zellen der Spalte //////////
                    var cellbox = tbl.rows[i].insertCell(tbl.rows[i].cells.length);
                    var box = document.createElement('input');
                    box.setAttribute('type', 'text');
                    box.setAttribute('name', 'column'+(tbl.rows[i].cells.length-1)+'[]');
                    box.setAttribute('id', (tbl.rows[i].cells.length-1)+'_'+(i-1));
                    box.setAttribute('size', '7');
                    box.setAttribute('maxlength', '15');
                    box.setAttribute('onChange', "javascript:calc_total('stats', this.parentNode.parentNode.rowIndex ,0);");
                    cellbox.appendChild(box);
                }    
                        
        }
          k = k+1;
      }
    
    /////// Tabellenreihe hinzufügen //////////
    function addRowToTable(id_name, col_string) 
      {
         var tbl = document.getElementById(id_name);
         var lastRow = tbl.rows.length;
         var row = tbl.insertRow(lastRow);
         row.setAttribute('id', 'row'+(lastRow-1));
         var col_array = col_string.split("-");
         
          
          /////// Zelle ID //////////
          var cellbox = row.insertCell(0);    
          var box = document.createElement('input');
          box.setAttribute('type', 'hidden');
          box.setAttribute('name', 'id'+'[]');
          box.setAttribute('size', '2');
          box.setAttribute('maxlength', '15');  
          cellbox.appendChild(box);
          
          /////// Zelle Monat //////////
          var cellbox = row.insertCell(1);    
          var box = document.createElement('input');
          box.setAttribute('type', 'text');
          box.setAttribute('name', 'column1[]');
          box.setAttribute('id', 1+'_'+(lastRow-1));
          box.setAttribute('size', '15');
          box.setAttribute('maxlength', '15'); 
          box.setAttribute('onChange', "javascript:calc_total('stats', this.parentNode.parentNode.rowIndex ,0);");          
          var hidden = document.createElement('input');
          hidden.setAttribute('type','hidden');
          hidden.setAttribute('name','selected_date[]');
          hidden.setAttribute('id','hidden_date_'+(lastRow-1));                 
          cellbox.appendChild(box);
          cellbox.appendChild(hidden);
          jQuery('#1_'+(lastRow-1)).datepicker({
                  changeMonth: true,
                  changeYear: true,
                  showButtonPanel: true,
                  dateFormat: 'MM yy',
                  monthNames: ["Januar", "Februar", "<? echo utf8_encode('März');?>", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember"],
                  monthNamesShort: ["Jan", "Feb", "M&auml;r", "Apr", "Mai", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dez"],
                  onClose: function(dateText, inst) { 
                      var month = jQuery('#be-ui-datepicker-div .be-ui-datepicker-month :selected').val();
                      var year = jQuery('#be-ui-datepicker-div .be-ui-datepicker-year :selected').val();
                      var date_ini = new Date(year,month, 1);
                      jQuery(this).datepicker('setDate', date_ini);
                      jQuery('#hidden_date_'+(lastRow-1)).val(date_ini.toGMTString());
                      }                                                  
      		});
      		jQuery('#1_'+(lastRow-1)).focus(function () {
                  jQuery('.be-ui-datepicker-calendar').hide();
                  });
          
          /////// Zelle Gesamteinnahmen //////////
          var cellbox = row.insertCell(2);    
          var box = document.createElement('input');
          box.setAttribute('type', 'text');
          box.setAttribute('name', 'column2[]');
          box.setAttribute('id', 2+'_'+(lastRow-1));
          box.setAttribute('size', '7');
          box.setAttribute('maxlength', '15'); 
          box.setAttribute('onChange', "javascript:calc_total('stats', this.parentNode.parentNode.rowIndex ,0);");                 
          cellbox.appendChild(box);
          document.getElementById(2+'_'+(lastRow-1)).readOnly = true;
          
          /////// Zelle eCPM //////////
          var cellbox = row.insertCell(3);    
          var box = document.createElement('input');
          box.setAttribute('type', 'text');
          box.setAttribute('name', 'column3[]');
          box.setAttribute('id', 3+'_'+(lastRow-1));
          box.setAttribute('size', '7');
          box.setAttribute('maxlength', '15'); 
          box.setAttribute('onChange', "javascript:calc_total('stats', this.parentNode.parentNode.rowIndex ,0);");                 
          cellbox.appendChild(box);
          document.getElementById(3+'_'+(lastRow-1)).readOnly = true;
          
          /////// sonstige Zellen der Reihe //////////    
         for (var i = 4; i < (tbl.rows[0].cells.length-1); i++)                          
          {
            var cellbox = row.insertCell(i);    
            var box = document.createElement('input');
            box.setAttribute('type', 'text');
            box.setAttribute('name', 'column'+(i)+'[]');
            box.setAttribute('id', i+'_'+(lastRow-1));
            box.setAttribute('size', '7');
            box.setAttribute('maxlength', '15'); 
            box.setAttribute('onChange', "javascript:calc_total('stats', this.parentNode.parentNode.rowIndex ,0);");                 
            cellbox.appendChild(box);  
          }
      }
      
    /////// Tabellenberechnungen //////////  
    function calc_total(table_id,row,change)
      {
        row--;
        var tbl = document.getElementById(table_id);
        var total = 0;
        var value = 0;
        var visits = 0;
        var ecpm = 0;

        for (var j = <? echo first_dynamic_column; ?> ; j < (tbl.rows[(row+1)].cells.length); j++)
          {
                if(document.getElementById(j +'_'+row) != null)                                                           
                 {   
                   //in der Usereingabe werden alle Kommatas durch Punkte ersetzt, weil die Werte sonst nicht numerisch sind
                   value = jQuery('#' + j +'_'+row).val();
                   value = value.split(',').join('.');
                   jQuery('#' + j +'_'+row).val(value);
                   //der Wert einer Einnahme wird zum Gesamtwert addiert, gerundet auf 2 Stellen nach dem Komma
                   value *= 100;
                   value = Math.ceil(value);
                   value = parseInt(value);
                   total *= 100;
                   total = total + value;
                   total = total/100;
                 } 
                         
          }
        //Berechnung des eCPM
        
        visits = parseInt(document.getElementById(5+'_'+row).value);
        ecpm = Math.round((total*100/visits)*1000)/100;
        
        document.getElementById('3_'+row).value = ecpm;
        document.getElementById('2_'+row).value = total; 
      }
    
                                
      
    /////// Tabellenspalte löschen //////////  
    function deleteColumn(id_name, col_num, admin_url)
      {
        var tbl = document.getElementById(id_name);
        var col_name = tbl.rows[0].cells[col_num].innerHTML;
        var label = col_name.split("<a href");
        
        var str = col_name.substr(0,6);
        
        if (str == '<input')
        {
          var check = confirm ("Wollen Sie diese Spalte wirklich entfernen?"); 
        }
        else
          {
            var check = confirm ("Wollen Sie die Spalte "+label[0]+" wirklich entfernen?"); 
          }
          
        if (check == true)
          {
             //die Tabelle wird reihenweise durchgegangen und die entsprechende Zelle aus der entsprechenden Spalte gelöscht
             for (var i = 0; i < (tbl.rows.length); i++)
              {                
                tbl.rows[i].deleteCell(col_num);                                                                      
                //für die nachfolgenden Zellen dieser Reihe muss die ID nach dem oben beschriebenen Muster geändert werden 
                if (i > 0)
                  {
                    for (var j = (col_num+1); j < (tbl.rows[i].cells.length+1); j++)
                    {
                      document.getElementById(j +'_'+(i-1)).id = (j-1)+'_'+(i-1);
                    }     
                    //die berechneten Werte der Tabelle müssen ebenfalls aktualisiert werden
                    calc_total('stats',i,1);                                                                      
                  }
              } 
            //Aufruf der Funktion zum Löschen der Spalte in der Datenbank       
            jQuery.post(admin_url+'/admin-ajax.php?action=delete_col', 
                            {
                              'action':'delete_col',
                              'data': col_name
                            }, 
                           function(response){
                           }
                      ); 
         //die Spaltennummer im Link zum Löschen der Spalte muss für alle folgenden Spalten um Eins verringert werden, damit beim nächsten Mal auch die richtige Spalte gelöscht wird   
         for (var i = (col_num); i < tbl.rows[1].cells.length; i++)
          {
            var col_label = tbl.rows[0].cells[i].innerHTML;
            var col_index = col_label.split(",");
            col_index[1]--;
            tbl.rows[0].cells[i].innerHTML = col_index[0]+','+col_index[1]+','+col_index[2];   
          }
        }                                                                                                
      }
    
    /////// Tabellenreihe löschen //////////  
    function deleteRow(id_name, row_num, admin_url)
      {
        row_num--;
        var tbl = document.getElementById(id_name);
        var row = document.getElementById('row'+row_num);        
        var id = document.getElementById('0_'+row_num).value;                                      
                                                                          
        var check = confirm ("Wollen Sie diese Reihe wirklich entfernen?"); 
        
        if (check == true)
          { 
            //anhand der ID, die jede Reihe hat, wird die ausgewählte Reihe gelöscht
            row.parentNode.removeChild(row);
            
             
            for (var i = (row_num+1); i < tbl.rows.length; i++)   //durchzählen der Reihen
              {
                  jQuery('#1_'+i).datepicker('destroy');  //der Kalender muss vorübergehend gelöscht werden. Besser gesagt die class hasDatepicker stört...
                  for (var k = 0; k < tbl.rows[1].cells.length; k++)    //durchzählen der Spalten
                    {
                        //die ID wird angepasst
                        document.getElementById(k+'_'+i).id = k+'_'+(i-1);
                    }
                  //die IDs der nachfolgenden Reihen und Monatsfelder werden angepasst
                  document.getElementById('row'+i).id = 'row'+(i-1);
                  document.getElementById('hidden_date_'+i).id = 'hidden_date_'+(i-1);                 
         
              }
              
            //der datepicker wird wieder für alle Monatsfelder hinzugefügt  
            for (var i = 0; i < (tbl.rows.length-1); i++)
              {
                  jQuery("#1_"+ i).datepicker({
                  changeMonth: true,
                  changeYear: true,
                  showButtonPanel: true,
                  dateFormat: 'MM yy',
                  monthNames: ["Januar", "Februar", "<? echo utf8_encode('März');?>", "April", "Mai", "Juni", "Juli", "August", "September", "Oktober", "November", "Dezember"],
                  monthNamesShort: ["Jan", "Feb", "M&auml;r", "Apr", "Mai", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dez"],
                  onClose: function(dateText, inst) { 
                      var month = jQuery('#be-ui-datepicker-div .be-ui-datepicker-month :selected').val();
                      var year = jQuery('#be-ui-datepicker-div .be-ui-datepicker-year :selected').val();
                      var date_ini = new Date(year,month, 1);
                      jQuery(this).datepicker('setDate', date_ini);
                      var id = this.id;
                      id = id.split('_');
                      jQuery('#hidden_date_'+id[1]).val(date_ini.toGMTString());
                      }
      		        });
      		
            		  jQuery('#1_'+i).focus(function () {
                        jQuery('.be-ui-datepicker-calendar').hide();
                        });       
                      
              }                   
            //Aufruf der Funktion zum Löschen der Reihe in der Datenbank
            jQuery.post(admin_url+'/admin-ajax.php?action=delete_row', 
                            {
                              'action':'delete_row',
                              'data': id
                            }, 
                           function(response){
                           }
                      );    
          } 
      }
     //wenn für einen Spaltennamen nur eine Zahl eingetragen wird, erscheint sofort ein alert-Fenster, um die Eingabe berichtigen zu können
     function isNumber(n) 
      {
        return !isNaN(parseFloat(n)) && isFinite(n);
      } 
      
     function numeric(num)
      {
         var object = document.getElementById('advert'+num);
         if(isNumber(object.value))
          {
            alert('FEHLER: Der Spaltenname darf kein numerischer Wert sein.');
          }             
      }
      
      
      
  </script>
  <?
  
  }

/////// Tabellenzeile in Datenbank löschen //////////
function delete_col()
  {
    global $wpdb;

    $col =  explode('<a href', $_POST['data']);
    mysql_query("ALTER TABLE ". $wpdb->prefix."blogeinnahmen DROP ".mysql_real_escape_string($col[0])."");
  }

/////// Tabellenreihe in Datenbank löschen //////////  
function delete_row()
  {
    global $wpdb;
 
    mysql_query("DELETE FROM ". $wpdb->prefix."blogeinnahmen WHERE id=".mysql_real_escape_string($_POST['data'])."");
  }
  
/////// Balkendiagramm zeichnen //////////
function bar_chart($attr)
  {
      global $wpdb;    
      
      /////// Daten verarbeiten //////////
      $result = mysql_query("Select * FROM ".$wpdb->prefix."blogeinnahmen WHERE month < ".mysql_real_escape_string($attr['currenttime'])." ORDER BY month") or Die (mysql_error());
  			while ($row = mysql_fetch_assoc($result))
  				{
  					$stats[] = $row;
  				}
      //die Datensätze $data1 und $data2 werden entsprechend zusammengebaut     
      $data1 = '[';
      $data2 = '[';
      if (count($stats) > $attr['time'])
        {
          $count = $attr['time'];
        }
        else
        {
          $count = count($stats);
        }
      for($i = 0; $i < $count; $i++)
        {                
            $month1 = $stats[$i]['month']*1000;  //die Multiplikation ist notwendig, weil der timestamp in Sekunden angegeben ist, aber das Diagramm in Millisekunden rechnet
            $data1 .= '['.$month1.', ';
            $data1 .= $stats[$i][$attr['cat1']].'], ';                
            $data2 .= '['.($month1 + 900000000).', ';   //die Addition bewirkt, dass die beiden Balken nebeneinander angezeigt werden
            $data2 .= $stats[$i][$attr['cat2']].'], ';
        }
      $data1 = substr($data1,0,-2);
      $data1 .= ']';
      //Anlegen einer Legende
      $legend1 = $attr['cat1'];
      switch ($legend1)
        {
          case 'total':
                $legend1 = 'Einnahmen';
                break;
          case 'visitors':
                $legend1 = 'Besucher';
                break;
          case 'clicks':
                $legend1 = 'PIs';
                break;
        }
      $data2 = substr($data2,0,-2);
      $data2 .= ']';
      $legend2 = $attr['cat2'];
      switch ($legend2)
        {
          case 'total':
                $legend2 = 'Einnahmen';
                break;
          case 'visitors':
                $legend2 = 'Besucher';
                break;
          case 'clicks':
                $legend2 = 'PIs';
                break;
        }
     $shortcode = 'Um diese Grafik in einem Blogartikel darzustellen, bitte folgenden Shortcode an entsprechender Stelle in den Artikel einsetzen: <br>   [blogeinnahmen_bar cat1="'.$attr['cat1'].'" cat2="'.$attr['cat2'].'" time="'.$attr['time'].'" currenttime="'.time().'"]';
        
     ?>   
      <script type="text/javascript">
      jQuery(document).ready(function(){
        var d1,d2,l1,l2, shortcode;
        <?
        if(isset($data1)){echo " d1=".$data1.";";}
    		if(isset($data2)){echo " d2=".$data2.";";}
    		if(isset($legend1)){echo " l1='".$legend1."';";}
    		if(isset($legend2)){echo " l2='".$legend2."';";}
    		if(isset($shortcode)){echo " shortcode='".$shortcode."';";}
    		?>
          
          /////// Diagramm definieren //////////                                                 
          jQuery.plot(jQuery("#placeholder"),[{data:d1, label: l1, color: "rgb(70,130,180)", yaxis: 1, lines: {show:false}},{data:d2, label: l2, color: "rgb(255,140,0)", yaxis:2, lines:{show:false}, points: {show:false}}], 
            {                                                                  
              xaxis:  { 
                        mode: "time", 
                        minTickSize: [1, "month"], 
                        timeformat: "%b/%y" 
                      },
              yaxes: [{},{position:"right"}],
              legend:  {
                          container: jQuery("#label_container")
                      },
              bars:   { 
                        show: true,                          
                        barWidth: 3600000*24*10,   //ein Balken ist 10 Tage "breit"
                        align: "left",
                        fillColor: { colors: [ { opacity: 0.9 }, { opacity: 0.1 } ] }        
                      },
              lines:  {
                        show:true
                      },
              grid:   {
                        hoverable: true    
                      }
           }
           );
         
         /////// Kurzinfo(hover) definieren //////////
         function showTooltip(x, y, contents) 
          {
            jQuery('<div id="tooltip">' + contents + '</div>').css( {
                position: 'absolute',
                display: 'none',
                top: y + 5,
                left: x + 5,
                border: '1px solid #fdd',
                padding: '2px',
                'background-color': '#fee',
                opacity: 0.80
            }).appendTo("body").fadeIn(200);
          }
         
         var previousPoint = null;  
         jQuery('#placeholder').bind("plothover", function (event, pos,item)
            {
              if (item) 
                {
                  if (previousPoint != item.dataIndex) 
                    {
                      previousPoint = item.dataIndex;
                      
                      jQuery("#tooltip").remove();
                      var x = item.datapoint[0],
                          y = item.datapoint[1].toFixed(2);
                      
                      var t = new Date(x);
                      var Monat = new Array("Jan", "Feb", "Mar", "Apr", "May", "Jun",
                                            "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");

                      showTooltip(item.pageX, item.pageY,
                                   Monat[t.getMonth()] + " " + t.getFullYear() + ", " + y);
                    }
               }                                             
              else 
                {
                  jQuery("#tooltip").remove();
                  previousPoint = null;            
                }
            } );
            
        jQuery("#shortcode_bar").html(shortcode);
        
      });
        
      </script>
      <?
      return '<div id="label_container" style="width:100px;"></div>                 
              <div id="placeholder" class="barchart" ></div>';     
  }

/////// Kreisdiagramm zeichnen //////////
function pie_chart($attr)
  {
    global $wpdb;
    $result = mysql_query("SHOW COLUMNS FROM ".$wpdb->prefix."blogeinnahmen ") or Die ('002');
			while ($row = mysql_fetch_assoc($result))
				{
					$columns[] = $row;
				}
			
    $pie_month = $attr['month'];
    
    if ( is_numeric($pie_month) && $pie_month > 0)
          {
            if ($pie_month > 12)
              {
                //der Fall, dass ein bestimmter Monat ausgewählt wurde. $pie_month ist dann nämlich ein timestamp der logischerweise größer als 12 ist.
                $result = mysql_query("SELECT * FROM ".$wpdb->prefix."blogeinnahmen WHERE month='".mysql_real_escape_string($pie_month)."'") ;                               
          			 while ($row = mysql_fetch_assoc($result))
          				{
          					$raw_data[] = $row;
          				} 
                $pie_data = '[';
                for($i = first_dynamic_column; $i < count($columns); $i++)
                  {
                         $pie_data .= "{ label: '".$columns[$i]['Field']."', data: ".$raw_data[0][$columns[$i]['Field']]."}, "; 
                  } 
                $pie_data = substr($pie_data,0, -2);
                $pie_data .= ']';
          		}
          		else
          		  {
                  //der Fall, dass ein Zeitraum ausgewählt wurde
                  $result = mysql_query("SELECT * FROM ".$wpdb->prefix."blogeinnahmen WHERE month < ".mysql_real_escape_string($attr['currenttime'])." ORDER BY month DESC") or Die(mysql_error());                               
            			 while ($row = mysql_fetch_assoc($result))
            				{
            					$raw_data[] = $row;
            				}
                    $total = array();
                    //die Werte des ausgewählten Zeitraums werden zusammengerechnet
                    for ($i = 0; $i < $pie_month; $i++)
                      {
                        for ($k = first_dynamic_column; $k < count($columns); $k++)           //ab Spalte 6 beginnen die Einnahmequellen, die für die Berechnung entscheidend sind
                          {
                            if (array_key_exists($columns[$k]['Field'],$total))
                              {
                                 $total[$columns[$k]['Field']] = $total[$columns[$k]['Field']] + $raw_data[$i][$columns[$k]['Field']];
                              }
                              else  
                                {
                                  $total[$columns[$k]['Field']] = $raw_data[$i][$columns[$k]['Field']];
                                }
                                 
                          }  
                      }
                       
                    $pie_data = '[';
                    for($i = first_dynamic_column; $i < count($columns); $i++)
                      {
                             $pie_data .= "{ label: '".$columns[$i]['Field']."', data: ".$total[$columns[$i]['Field']]."}, "; 
                      } 
                    $pie_data = substr($pie_data,0, -2);
                    $pie_data .= ']';
                }
         }
      $shortcode = 'Um diese Grafik in einem Blogartikel darzustellen, bitte folgenden Shortcode an entsprechender Stelle in den Artikel einsetzen: <br> [blogeinnahmen_pie month="'.$attr['month'].'" currenttime="'.time().' "]';   
         
      ?>   
      <script type="text/javascript">
      jQuery(document).ready(function(){ 
         var p1,shortcode;
          <?php
            if(isset($pie_data)){echo " p1=".$pie_data.";";}
            if(isset($shortcode)){echo " shortcode='".$shortcode."';";}
          ?>  
          jQuery.plot(jQuery("#pie_place"), p1, 
              { 
                series: {
                        pie:{
                            show:true,
                            innerRadius: 0.35,   //bewirkt "Donut"-Form
                            radius: 3/4,
                            label: {
                                    show: true,
                                    radius: 3/4,
                                    formatter: function(label, series){
                                        return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'+label+'<br/>'+Math.round(series.percent)+'%</div>';
                                    },
                                    background: {
                                                opacity: 0.5,
                                                color: '#000'
                                                }
                                  }
                            }
                        },
                legend:{
                        show:false
                       }
              });
              
          jQuery("#shortcode_pie").html(shortcode);         
         });
                   
      </script>
      <?
      return '<div id="pie_place" class="piechart" ></div>';
  }

 /////// Tabelle erstellen bei der ersten Aktivierung //////////
function create_datatable()
  {
    global $wpdb;
    $table_name = $wpdb->prefix."blogeinnahmen";
    $sql = "CREATE TABLE ". $table_name ." (
                  id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
                  month INT,
                  total DOUBLE(6,2),
                  eCPM DOUBLE(6,2),
                  visitors INT,
                  clicks INT)";
   mysql_query($sql);
  }

/////// Tabellenspalte zur Datenbank hinzufügen //////////  
function update_table($column_name)
  {
    global $wpdb;
    if ($column_name != '')
    {
      $column_name = mysql_real_escape_string($column_name);
      
      $table_name = $wpdb->prefix."blogeinnahmen";
      $sql = "ALTER TABLE ". $table_name ." ADD ".$column_name." DOUBLE (6,2)";
      $mysql_error = mysql_query($sql);
      if ($mysql_error === false)
      {
        echo "Fehler: Die Bezeichnung einer Spalte kann nicht verwendet werden." ;
        exit();
      }
    }  
  }
  
  
?>