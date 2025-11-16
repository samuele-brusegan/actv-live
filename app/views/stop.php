<?php
if (!isset($_COOKIE)) {
    echo "<pre>"; print_r($_COOKIE); echo "</pre>";
}
if ( !isset($_GET) || !isset($_GET['id']) ) {
	echo "_GET or id, not setted";
	exit;
}
$stopId = $_GET['id'];

// echo "<pre>"; print_r($_GET); echo "</pre>";
if(!isset($resp)) $resp = "err404";

//https://oraritemporeale.actv.it/aut/backend/passages/167-web-aut

$ch = curl_init("https://oraritemporeale.actv.it/aut/backend/page/stops");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$stopsList = json_decode(curl_exec($ch), true);
curl_close($ch);

$ch = curl_init("https://oraritemporeale.actv.it/aut/backend/passages/".$stopId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$passages = json_decode(curl_exec($ch), true);
curl_close($ch);

//Find name(id) - description
$stopName = "";
$currentStop = null;
foreach($stopsList as $stop){
	if($stop['name'] == $stopId){
		$stopName = $stop['description'];
        $currentStop = $stop;
		break;
	}
}


?>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>ACTV Stop - <?=$stopId?></title>
		<?php require COMMON_HTML_HEAD ?>
		
	</head>
	<body>
		<div class="container-desktop" id="layout">
			<div data-v-93878d43="" class="desktop-topbar" style="--radius: 0;">
				<a data-v-93878d43="" href="/" rel="noopener noreferrer" target="_parent" class="logo">
					<div data-v-e59c4665="" data-v-93878d43="" class="icon-wrapper" style="--3ba3f6f5: auto; --1f0bedd8: 100%; height:100% width:auto">
						<img data-v-e59c4665="" src="<?=URL_PATH?>/svg/logo-icon.svg" alt="Logo">
					</div>
				</a>
			</div>
			<div data-v-6bf8e597="" class="page">
				<div data-v-aaf446aa="" data-v-6bf8e597="" class="topbar">
					<div data-v-aaf446aa="" class="left">
						<a data-v-aaf446aa="" href="/stopList" class="material-symbols-rounded color-main pointer">
                            <img src="<?=URL_PATH?>/svg/arrow_back.svg" alt="">
                        </a>
					</div>
					<div data-v-aaf446aa="" class="center text-regular bold uppercase color-main"></div>
					<div data-v-aaf446aa="" class="right">
						<span data-v-aaf446aa="" class="material-symbols-rounded color-main pointer" id="favorite">
                            <img src="<?=URL_PATH?>/svg/star.svg" alt="">
                        </span>
					</div>
				</div>
				<div data-v-6bf8e597="" class="heading">
					<h1 data-v-6bf8e597="" class="text-large bold color-main" style="view-transition-name: stopname-4586-4587-web-aut;"><?=$stopName?></h1>
					<div data-v-6bf8e597="" class="filter-wrap" style="height: auto;">
						<div data-v-6bf8e597="" class="scroll-wrapper">
							<div data-v-6bf8e597="" class="filter_block scroll">

                                <?php

                                for($i = 0; $i < sizeof($currentStop['lines']); $i++) {

                                    ?>
                                    <div data-v-6bf8e597="" class="stop_line pointer alternate">
                                        <span data-v-6bf8e597="" class="material-symbols-rounded">
                                            <img src="<?=URL_PATH?>/svg/directions_bus.svg" alt="">
                                        </span>
                                        <span data-v-6bf8e597="" class="text-regular bold"><?=$currentStop['lines'][$i]['alias']?></span>
                                    </div>
                                    <?php

                                }

                                ?>
							</div>
						</div>
					</div>
				</div>
				<div data-v-6bf8e597="" class="passages glass">
					<h2 data-v-6bf8e597="" class="text-regular align-center bold color-main uppercase">Prossime partenze</h2>
					<p data-v-6bf8e597="" class="legend">
						<span data-v-6bf8e597="" class="text-large material-symbols-rounded">
                            <img src="<?=URL_PATH?>/svg/share_location.svg" alt="">
                        </span>
						<span data-v-6bf8e597="" class="text-regular">Orari in tempo reale</span>
						<span data-v-6bf8e597="" class="text-large material-symbols-rounded">
                            <img src="<?=URL_PATH?>/svg/update.svg" alt="">
                        </span>
						<span data-v-6bf8e597="" class="text-regular">Orari programmati</span>
					</p>
					<table data-v-6bf8e597="">
						<thead data-v-6bf8e597="">
						<tr data-v-6bf8e597="">
							<th data-v-6bf8e597="" class="bold color-main">Partenza</th>
							<th data-v-6bf8e597="" class="bold color-main">Linea</th>
							<th data-v-6bf8e597="" class="bold color-main">Fermata</th>
							<th data-v-6bf8e597="" class="bold color-main">Destinazione</th>
						</tr>
						</thead>
						<tbody data-v-6bf8e597="" id="passages">

                            <?php
                            for($i = 0; $i < sizeof($passages); $i++) {
                                $currentPassage = $passages[$i];
                                ?>
                                <tr data-v-82e7a48c="" data-v-6bf8e597="" class="pointer">
                                    <td data-v-82e7a48c="">
                                        <div data-v-82e7a48c="" class="time">
                                            <span data-v-82e7a48c="" class="text-xregular material-symbols-rounded" style="height: 25px; width: 25px;">
                                                <?php
                                                if(!$currentPassage['real']) {
                                                    ?>
                                                    <img style="aspect-ratio: 1; height: 24px;width: 24px;" src="<?=URL_PATH?>/svg/update.svg" alt="">
                                                    <?php
                                                } else {
                                                    ?>
                                                    <img style="aspect-ratio: 1; height: 24px;width: 24px;" src="<?=URL_PATH?>/svg/share_location.svg" alt="">
                                                    <?php
                                                }
                                                ?>
                                            </span>
                                            <span data-v-82e7a48c="" class="text-regular bold"><?=$currentPassage['time']?></span>
                                        </div>
                                    </td>
                                    <td data-v-82e7a48c="">
                                        <img data-v-d1106676="" data-v-82e7a48c="" class="line-img" src="https://oraritemporeale.actv.it/aut/lines/<?=$currentPassage['line']?>.png?>" alt="<?=$currentPassage['line']?>">
                                    </td>
                                    <td data-v-82e7a48c="" class="stop">
                                        <span data-v-d1106676="" data-v-82e7a48c="" class="text-regular bold"><?=$currentPassage['lineId']?></span>
                                    </td>
                                    <td data-v-82e7a48c="">
                                        <span data-v-82e7a48c="" class="text-regular bold"><?=$currentPassage['destination']?></span>
                                        <div data-v-82e7a48c="" class="description">
                                            <div data-v-82e7a48c="" class="text-small bold uppercase" style="color: #999; overflow: clip; white-space: nowrap;"><?=$currentPassage['path']?></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
						</tbody>
					</table>
					<div data-v-6bf8e597="" class="footer-passages align-center">
						<button data-v-12055c0b="" data-v-6bf8e597="" class="button pointer main round" id="refresh-btn">
							<span data-v-12055c0b="" class="uppercase bold">Visualizza successive</span>
							<span data-v-12055c0b="" class="material-symbols-rounded">
                                <img src="<?=URL_PATH?>/svg/refresh.svg" alt="">
                            </span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</body>
    <script>
        const stopId = "<?=$stopId?>";
		console.log( "stopId: ", stopId)

		// add to local storage
        function toggleFavorite() {

			function addFavorite(stopId) {
				// add to local storage
				localStorage.setItem("favoriteStops", JSON.stringify(
					[
						...JSON.parse(localStorage.getItem("favoriteStops") || "[]"),
						stopId
					]
				));
				document.getElementById("favorite").src = '<?=URL_PATH?>/svg/star_fill.svg';
            }
			function removeFavorite(stopId) {
				// remove from local storage
				localStorage.setItem("favoriteStops", JSON.stringify(
					localStorage.getItem("favoriteStops").split(",").filter(
						item => item !== stopId)
				));
				//remove filled star
				document.getElementById("favorite").src = '<?=URL_PATH?>/svg/star.svg';
            }

			if (localStorage.getItem("favoriteStops") == null || !localStorage.getItem("favoriteStops").includes(stopId)) {
				addFavorite(stopId);
            }

			if (localStorage.getItem("favoriteStops").includes(stopId)) {
				removeFavorite(stopId);
			}
		}
		async function autoUpdate() {
			try {
				let response = await fetch("https://oraritemporeale.actv.it/aut/backend/passages/"+stopId);
				if (!response.ok) {
					throw new Error(`Response status: ${response.status}`);
				}

				return await response.json();

			} catch (error) {
				console.error(error.message);
			}
		}
		function updatePassages(data) {
			let tbody = document.getElementById("passages");
			tbody.innerHTML = "";

			for(let i = 0; i < data.length; i++) {
				let currentPassage = data[i];
				let row = document.createElement("tr");
                let imgSrc = "<?=URL_PATH?>/svg/update.svg";
				if (currentPassage.real) imgSrc = "<?=URL_PATH?>/svg/share_location.svg";
				let eta_time = currentPassage.time;
				let line = currentPassage.line;
				let lineId = currentPassage.lineId;
				let destination = currentPassage.destination;
				let path = currentPassage.path;

				row.innerHTML = `
					<tr data-v-82e7a48c="" data-v-6bf8e597="" class="pointer">
                        <td data-v-82e7a48c="">
                            <div data-v-82e7a48c="" class="time">
                                <span data-v-82e7a48c="" class="text-xregular material-symbols-rounded" style="height: 25px; width: 25px;">
                                    <img style="aspect-ratio: 1; height: 24px;width: 24px;" src="${imgSrc}" alt="">
                                </span>
                                <span data-v-82e7a48c="" class="text-regular bold">${eta_time}</span>
                            </div>
                        </td>
                        <td data-v-82e7a48c="">
                            <img data-v-d1106676="" data-v-82e7a48c="" class="line-img" src="https://oraritemporeale.actv.it/aut/lines/${line}.png?>" alt="${line}">
                        </td>
                        <td data-v-82e7a48c="" class="stop">
                            <span data-v-d1106676="" data-v-82e7a48c="" class="text-regular bold">${lineId}</span>
                        </td>
                        <td data-v-82e7a48c="">
                            <span data-v-82e7a48c="" class="text-regular bold">${destination}</span>
                            <div data-v-82e7a48c="" class="description">
                                <div data-v-82e7a48c="" class="text-small bold uppercase" style="color: #999; overflow: clip; white-space: nowrap;">${path}</div>
                            </div>
                        </td>
                    </tr>
				`;
				tbody.appendChild(row);
			}
        }

		document.getElementById("favorite").addEventListener("click", toggleFavorite);
		document.getElementById("refresh-btn").addEventListener("click", async () => {
            updatePassages( await autoUpdate() );
		});

		setInterval(autoUpdate, 15000);

    </script>
</html>
