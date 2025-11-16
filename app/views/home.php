<?php

// 1. Get file at `https://oraritemporeale.actv.it/aut/backend/page/stops`
$ch = curl_init("https://oraritemporeale.actv.it/aut/backend/page/stops");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = curl_exec($ch);
if ($resp === false) {
    echo 'Curl error: ' . curl_error($ch);
}
curl_close($ch);

$resp = json_decode($resp, true);
/*echo "<pre style='background: #0000; border : 0;'>";
print_r($resp[0]);
echo "</pre>";*/
// 2. Get file at `https://oraritemporeale.actv.it/aut/backend/page/[stop1]-[stop2]-web-aut`
//https://oraritemporeale.actv.it/aut/backend/passages/167-web-aut

?>

<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Bytelab - Todos</title>
        <?php require COMMON_HTML_HEAD ?>
    </head>
    <style>


    </style>
    <body>
        <div class="container-desktop">
            <div class="page">
                <div class="stop_results">
                    <?php
                    //Ordino $resp per la proprietà description
                    usort($resp, function($a, $b) {
                        return strcmp($a['description'], $b['description']);
                    });

                    for($i = 0; $i < sizeof($resp); $i++) {
                        $currentStop = $resp[$i];
                        if(sizeof($currentStop['lines']) == 0) continue;
                        ?>
                        <a data-v-02845fd5="" data-v-996d2d02="" href="/aut/stops/stop?id=<?=$currentStop['name']?>" class="stop_single_result glass pointer" style="--index: 1;">
                            <div data-v-02845fd5="" class="stop_head">
                                <div data-v-02845fd5="" class="name_wrap">
                                    <span data-v-02845fd5="" class="stop_name" style="view-transition-name: stopname-<?=$currentStop['name']?>;"><?=$currentStop['description']?></span>
                                </div>
                            </div>
                            <div data-v-02845fd5="" class="stop_lines">
                                <?php
                                for($j = 0; $j < sizeof($currentStop['lines']); $j++) {
                                    ?>
                                    <span data-v-02845fd5="" class="stop_line alternate small">
                                        <span data-v-02845fd5="" class="material-symbols-rounded text-xregular">
                                            <img src="<?=URL_PATH?>/svg/directions_bus.svg" alt="">
                                        </span>
                                        <span data-v-02845fd5="" class="text-regular bold"><?=$currentStop['lines'][$j]['alias']?></span>
                                    </span>
                                    <?php
                                }
                                ?>
                            </div>
                        </a>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </body>
</html>
