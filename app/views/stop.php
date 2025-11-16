<?php
if ( !isset($_GET) || !isset($_GET['id']) ) {
	echo "_GET or id, not setted";
	exit;
}
$stopId = $_GET['id'];

// echo "<pre>"; print_r($_GET); echo "</pre>";
if(!isset($resp)) $resp = "err404";




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
				<a data-v-93878d43="" href="/aut" rel="noopener noreferrer" target="_parent" class="logo">
					<div data-v-e59c4665="" data-v-93878d43="" class="icon-wrapper" height="100%" width="auto" style="--3ba3f6f5: auto; --1f0bedd8: 100%;">
						<img data-v-e59c4665="" src="https://oraritemporeale.actv.it/aut/logo-icon.png">
					</div>
				</a>
			</div>
			<div data-v-6bf8e597="" class="page">
				<div data-v-aaf446aa="" data-v-6bf8e597="" class="topbar">
					<div data-v-aaf446aa="" class="left">
						<a data-v-aaf446aa="" href="/" class="material-symbols-rounded color-main pointer"> arrow_back </a>
					</div>
					<div data-v-aaf446aa="" class="center text-regular bold uppercase color-main"></div>
					<div data-v-aaf446aa="" class="right">
						<span data-v-aaf446aa="" class="material-symbols-rounded color-main pointer">star</span>
					</div>
				</div>
				<div data-v-6bf8e597="" class="heading">
					<h1 data-v-6bf8e597="" class="text-large bold color-main" style="view-transition-name: stopname-4586-4587-web-aut;">Alberoni Ca' Rossa [4586] [4587]</h1>
					<div data-v-6bf8e597="" class="filter-wrap" style="height: auto;">
						<div data-v-6bf8e597="" class="scroll-wrapper">
							<div data-v-6bf8e597="" class="filter_block scroll">
								<div data-v-6bf8e597="" class="stop_line pointer alternate">
									<span data-v-6bf8e597="" class="material-symbols-rounded">directions_bus</span>
									<span data-v-6bf8e597="" class="text-regular bold">11</span>
								</div>
								<div data-v-6bf8e597="" class="stop_line pointer alternate">
									<span data-v-6bf8e597="" class="material-symbols-rounded">directions_bus</span>
									<span data-v-6bf8e597="" class="text-regular bold">A</span>
								</div>
								<div data-v-6bf8e597="" class="stop_line pointer alternate">
									<span data-v-6bf8e597="" class="material-symbols-rounded">directions_bus</span>
									<span data-v-6bf8e597="" class="text-regular bold">N</span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div data-v-6bf8e597="" class="passages glass">
					<h2 data-v-6bf8e597="" class="text-regular align-center bold color-main uppercase">Prossime partenze</h2>
					<p data-v-6bf8e597="" class="legend">
						<span data-v-6bf8e597="" class="text-large material-symbols-rounded">share_location</span>
						<span data-v-6bf8e597="" class="text-regular">Orari in tempo reale</span>
						<span data-v-6bf8e597="" class="text-large material-symbols-rounded">update</span>
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
						<tbody data-v-6bf8e597="">
							<tr data-v-82e7a48c="" data-v-6bf8e597="" class="pointer">
								<td data-v-82e7a48c="">
									<div data-v-82e7a48c="" class="time">
										<span data-v-82e7a48c="" class="text-xregular material-symbols-rounded">share_location</span>
										<span data-v-82e7a48c="" class="text-regular bold">00:25</span>
									</div>
								</td>
								<td data-v-82e7a48c="">
									<img data-v-d1106676="" data-v-82e7a48c="" class="line-img" src="https://oraritemporeale.actv.it/aut/lines/A_UL.png">
								</td>
								<td data-v-82e7a48c="" class="stop">
									<span data-v-d1106676="" data-v-82e7a48c="" class="text-regular bold">4586</span>
								</td>
								<td data-v-82e7a48c="">
									<span data-v-82e7a48c="" class="text-regular bold">F.ROCCHETTA</span>
									<div data-v-82e7a48c="" class="description">
										<div data-v-82e7a48c="" class="text-small bold uppercase">Faro Rocchetta</div>
									</div>
								</td>
							</tr>
							<tr data-v-82e7a48c="" data-v-6bf8e597="" class="pointer">
								<td data-v-82e7a48c="">
									<div data-v-82e7a48c="" class="time">
										<span data-v-82e7a48c="" class="text-xregular material-symbols-rounded">share_location</span>
										<span data-v-82e7a48c="" class="text-regular bold">00:37</span>
									</div>
								</td>
								<td data-v-82e7a48c="">
									<img data-v-d1106676="" data-v-82e7a48c="" class="line-img" src="https://oraritemporeale.actv.it/aut/lines/A_UL.png">
								</td>
								<td data-v-82e7a48c="" class="stop">
									<span data-v-d1106676="" data-v-82e7a48c="" class="text-regular bold">4587</span>
								</td>
								<td data-v-82e7a48c="">
									<span data-v-82e7a48c="" class="text-regular bold">S.M.E.</span>
									<div data-v-82e7a48c="" class="description fademask">
										<div data-v-82e7a48c="" class="text-small bold scrollable uppercase" style="translate: none; rotate: none; scale: none; transform: translate3d(-19.8789%, 0px, 0px);">IRCCS S.Camillo-Malamocco-Ca' Bianca-v.Sandro Gallo-P.LE SANTA MARIA ELISABETTA-Gran Viale S.M.E.-Lung. D'Annunzio-P.le Rava'/Ospedale al Mare</div>
									</div>
								</td>
							</tr>
							<tr data-v-82e7a48c="" data-v-6bf8e597="" class="pointer">
								<td data-v-82e7a48c="">
									<div data-v-82e7a48c="" class="time">
										<span data-v-82e7a48c="" class="text-xregular material-symbols-rounded">share_location</span>
										<span data-v-82e7a48c="" class="text-regular bold">00:43</span>
									</div>
								</td>
								<td data-v-82e7a48c="">
									<img data-v-d1106676="" data-v-82e7a48c="" class="line-img" src="https://oraritemporeale.actv.it/aut/lines/N_UL.png">
								</td>
								<td data-v-82e7a48c="" class="stop">
									<span data-v-d1106676="" data-v-82e7a48c="" class="text-regular bold">4586</span>
								</td>
								<td data-v-82e7a48c="">
									<span data-v-82e7a48c="" class="text-regular bold">PELLESTRINA</span>
									<div data-v-82e7a48c="" class="description fademask">
										<div data-v-82e7a48c="" class="text-small bold scrollable uppercase" style="translate: none; rotate: none; scale: none; transform: translate3d(-37.1421%, 0px, 0px);">Alberoni - Della Droma - Zaffi da Barca - Dei Murazzi - P.le Caduti Giudecca</div>
									</div>
								</td>
							</tr>
							<tr data-v-82e7a48c="" data-v-6bf8e597="" class="pointer">
								<td data-v-82e7a48c="">
									<div data-v-82e7a48c="" class="time">
										<span data-v-82e7a48c="" class="text-xregular material-symbols-rounded">share_location</span>
										<span data-v-82e7a48c="" class="text-regular bold">00:44</span>
									</div>
								</td>
								<td data-v-82e7a48c="">
									<img data-v-d1106676="" data-v-82e7a48c="" class="line-img" src="https://oraritemporeale.actv.it/aut/lines/N_UL.png">
								</td>
								<td data-v-82e7a48c="" class="stop">
									<span data-v-d1106676="" data-v-82e7a48c="" class="text-regular bold">4587</span>
								</td>
								<td data-v-82e7a48c="">
									<span data-v-82e7a48c="" class="text-regular bold">via Ca' Rossa</span>
									<div data-v-82e7a48c="" class="description fademask">
										<div data-v-82e7a48c="" class="text-small bold scrollable uppercase" style="translate: none; rotate: none; scale: none; transform: translate3d(-35.7316%, 0px, 0px);">IRCCS S.Camillo-Malamocco-Ca' Bianca-v.Sandro Gallo-P.LE SANTA MARIA ELISABETTA</div>
									</div>
								</td>
							</tr>
							<tr data-v-82e7a48c="" data-v-6bf8e597="" class="pointer">
								<td data-v-82e7a48c="">
									<div data-v-82e7a48c="" class="time">
										<span data-v-82e7a48c="" class="text-xregular material-symbols-rounded">share_location</span>
										<span data-v-82e7a48c="" class="text-regular bold">01:04</span>
									</div>
								</td>
								<td data-v-82e7a48c="">
									<img data-v-d1106676="" data-v-82e7a48c="" class="line-img" src="https://oraritemporeale.actv.it/aut/lines/N_UL.png">
								</td>
								<td data-v-82e7a48c="" class="stop">
									<span data-v-d1106676="" data-v-82e7a48c="" class="text-regular bold">4586</span>
								</td>
								<td data-v-82e7a48c="">
									<span data-v-82e7a48c="" class="text-regular bold">F.ROCCHETTA</span>
									<div data-v-82e7a48c="" class="description">
										<div data-v-82e7a48c="" class="text-small bold uppercase">Alberoni - Della Droma - Zaffi da Barca</div>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
					<div data-v-6bf8e597="" class="footer-passages align-center">
						<button data-v-12055c0b="" data-v-6bf8e597="" class="button pointer main round">
							<span data-v-12055c0b="" class="uppercase bold">Visualizza successive</span>
							<span data-v-12055c0b="" class="material-symbols-rounded">refresh</span>
						</button>
					</div>
				</div>
			</div>
		</div>
	</body>
</html>
