<?php
$data = json_decode(file_get_contents('https://oraritemporeale.actv.it/aut/backend/page/stops'), true);
for ($i = 0; $i < 10; $i++) {
    if (isset($data[$i])) {
        echo $data[$i]['description'] . " -> Terminal: " . json_encode($data[$i]['terminal']) . "\n";
    }
}
?>
