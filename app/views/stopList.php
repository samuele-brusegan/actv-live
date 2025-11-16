<?php

// 1. Get file at `https://oraritemporeale.actv.it/aut/backend/page/stops`
$ch = curl_init("https://oraritemporeale.actv.it/aut/backend/page/stops");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

?>

<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Orari in Tempo reale ACTV</title>
        <?php require COMMON_HTML_HEAD ?>
    </head>
    <style>


    </style>
    <body>
        <div class="container-desktop">
            <div data-v-93878d43="" class="desktop-topbar" style="--radius: 0;">
                <a data-v-93878d43="" href="/" rel="noopener noreferrer" target="_parent" class="logo">
                    <div data-v-e59c4665="" data-v-93878d43="" class="icon-wrapper" style="--3ba3f6f5: auto; --1f0bedd8: 100%; height: 100%; width: auto;">
                        <img data-v-e59c4665="" src="<?=URL_PATH?>/svg/logo-icon.svg" alt="Logo">
                    </div>
                </a>
            </div>
            <div class="page">
                <div data-v-996d2d02="" class="searchbar glass">
                    <a data-v-996d2d02="" href="/" rel="noopener noreferrer" target="_parent" class="material-symbols-rounded color-main pointer">
                        <img src="<?=URL_PATH?>/svg/arrow_back.svg" alt="Arrow back">
                    </a>
                    <!--suppress HtmlFormInputWithoutLabel -->
                    <input data-v-996d2d02="" type="text" placeholder="Cerca la fermata" id="searchbar">
                    <span data-v-996d2d02="" class="material-symbols-rounded color-main">
                        <img src="<?=URL_PATH?>/svg/search.svg" alt="Search">
                    </span>
                </div>
                <div class="stop_results" style="margin-top: 25px;">
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
    <script>
        const searchbar = document.getElementById("searchbar");

		searchbar.addEventListener("input", function() {
            const value = this.value.toLowerCase();
            const elements = document.querySelectorAll(".stop_single_result");
            elements.forEach(element => {
                const name = element.querySelector(".stop_name").textContent.toLowerCase();
                element.style.display = name.includes(value) ? "block" : "none";
            });
        });
    </script>
</html>
