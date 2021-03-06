<?php

include_once("connections/connection.php");
$con = connection();

$mode = $_REQUEST["mode"];

$sliceDirector = $_REQUEST["sliceDirector"];
$sliceActor = $_REQUEST["sliceActor"];
$sliceMovie = $_REQUEST["sliceMovie"];

$WHERE = "";
$AND1 = "";
$AND2 = "";

$nSlices = 0;
if ($sliceDirector != ""){
    $nSlices++;
}
if ($sliceActor != ""){
    $nSlices++;
}
if ($sliceMovie != ""){
    $nSlices++;
}

echo  $nSlices;
switch ($nSlices){
    case 3: $AND2 = " AND ";
    case 2: 
        if ($sliceDirector == ""){
            $AND2 = " AND ";
        } else {
            $AND1 = " AND ";
        }
    case 1: $WHERE = " WHERE "; break;
    case 0:break;
}
echo 'Hello:' . $WHERE . $sliceDirector . $AND1 . $sliceActor . $AND2 . $sliceMovie;

$pageNum = $_REQUEST["pageNum"] - 1;
$pageLimit = 20;
$offset = $pageNum * $pageLimit;

switch ($mode) {
    case "rollupDirector":
        $sql = 'SELECT
                    directors.full_name as Director
                    ,director_id
                    ,Rating
                FROM(
                    SELECT
                        director_id
                        ,TRUNCATE(avg(ranks.rank),2) Rating
                    FROM
                        ranks
                    '. $WHERE . $sliceDirector . $AND1 . $sliceActor . $AND2 . $sliceMovie .'
                    GROUP BY 
                        director_id
                    WITH ROLLUP
                    LIMIT ' . $pageLimit . ' OFFSET ' . $offset.') as selectedRanks
                LEFT JOIN directors USING (director_id)
                ORDER BY
                    director_id IS NULL, director_id asc
                ';
        break;
    case "rollupActor":
        $sql = 'SELECT
                    directors.full_name as Director
                    ,director_id
                    ,actors.full_name as Actor
                    ,actor_id
                    ,Rating
                FROM(
                    SELECT
                        director_id
                        ,actor_id
                        ,TRUNCATE(avg(ranks.rank),2) Rating
                    FROM
                        ranks
                    '. $WHERE . $sliceDirector . $AND1 . $sliceActor . $AND2 . $sliceMovie .'
                    GROUP BY 
                        director_id
                        ,actor_id
                    WITH ROLLUP
                    LIMIT ' . $pageLimit . ' OFFSET ' . $offset.') as selectedRanks
                LEFT JOIN directors USING (director_id)
                LEFT JOIN actors USING (actor_id)
                ORDER BY
                    director_id IS NULL, director_id asc
                    , actor_id IS NULL, actor_id asc
                ';
        break;
    case "rollupMovie":
        $sql = 'SELECT
                    directors.full_name as Director
                    ,director_id
                    ,actors.full_name as Actor
                    ,actor_id
                    ,movies.name as Movie
                    ,movie_id
                    ,Rating
                FROM(
                    SELECT
                        director_id
                        ,actor_id
                        ,movie_id
                        ,TRUNCATE(avg(ranks.rank),2) Rating
                    FROM
                        ranks
                    '. $WHERE . $sliceDirector . $AND1 . $sliceActor . $AND2 . $sliceMovie .'
                    GROUP BY 
                        director_id
                        ,actor_id
                        ,movie_id
                    WITH ROLLUP
                    LIMIT ' . $pageLimit . ' OFFSET ' . $offset.') as selectedRanks
                LEFT JOIN directors USING (director_id)
                LEFT JOIN actors USING (actor_id)
                LEFT JOIN movies USING (movie_id)
                ORDER BY
                    director_id IS NULL, director_id asc
                    , actor_id IS NULL, actor_id asc
                    , movie_id IS NULL, movie_id asc
                ';
        break;
}


$result = $con->query($sql) or die($con->connect_error);

$finfo = $result->fetch_fields();
$numFields = count($finfo);

echo '<thead class="thead-dark">';
echo "<th>#</th>";
foreach ($finfo as $val) {
    if (substr_compare($val->name, "_id", -3) != 0){
        echo "<th>" .  $val->name . "</th>";
    }
}
echo "</thead>";
$rowCount = 0;
while ($row = $result->fetch_array()) {

    $rollup = 0;
    for($i = 0; $i < $numFields; $i+=2){
        if ($row[$i] == ""){
            $rollup++;
        }
    }

    echo '<tr>';
    $rowCount++;
    echo "<td>" . ($offset+$rowCount) . "</td>";

    $rollupClass = "";
    for($i = 0; $i < $numFields; $i+=2){
        $clickableClass = "";
        $onclick = "";
        $strName = "'".$row[$i]."'";
        $strColumn = "'".$finfo[$i+1]->name."'";
        if($i+1 < $numFields && $row[$i] != ""){    
            // $onclick = 'onclick="slice(' . $row[$i]+1 . ','.addslashes($strName).')"'; // onlick="slice(22,'Veikko Aaltonen')"
            $onclick = 'onclick="slice('. $row[$i+1] .','. $strName .','. $strColumn .')"';
            $clickableClass = " clickable";
        }
        // $numFields - $i == $rollup+2
        if($i == $numFields-3-($rollup*2)){   
            $rollupClass .= " rollup".$rollup;
        }

        echo '<td class="'.$rollupClass.$clickableClass.'" '.$onclick.' >' . $row[$i] . '</td>';
    }
    echo "</tr>";
};