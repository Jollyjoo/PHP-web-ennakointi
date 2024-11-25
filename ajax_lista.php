<?php
// Array with names

$a[] = "Päijät-Häme	Julkinen talous	22.10.2024		Hartolan kunta nostaa veroprosenttiaan:	https://www.itahame.fi/paikalliset/8000976<br>";
$a[] = "Päijät-Häme	Julkinen talous	22.10.2024		Hartola nostaa veroprosenttiaan  kunnan tuloveroprosentti pysyy edelleen Päijät-Hämeen toiseksi korkeimpana:	https://www.ess.fi/paikalliset/8001997<br>  ";
$a[] = "Päijät-Häme	Julkinen talous	10.10.2024		Lahti sulkee yhden lähikirjastoistaan  Launeen kirjaston toiminta lakkaa:	https://yle.fi/a/74-20116990 <br> ";
$a[] = "Päijät-Häme	Julkinen talous	10.10.2024		Lahtelaisten kotikadut ovat talvella entistä lumisempia ja kapeampia, jos säästöleikkuri iskee aurauksiin:	https://yle.fi/a/74-20117189<br>  ";
$a[] = "Päijät-Häme	Julkinen talous	11.10.2024		Lahti joutuu ottamaan syömävelkaa ensi vuonna  yt-neuvottelut alkavat heti tammikuussa:	https://yle.fi/a/74-20117360 <br> ";
$a[] = "Päijät-Häme	Julkinen talous	14.10.2024		Lahti Energia saattaa joutua ottamaan lainaa osingonmaksuun  toimitusjohtaja: ”Onko tässä mitään järkeä”:	https://yle.fi/a/74-20117733<br>  ";
$a[] = "Päijät-Häme	Julkinen talous	17.10.2024		Talveksi luvassa polanteita ja polkuja katujen talvikunnossapito heikkenee Lahdessa:	https://www.ess.fi/paikalliset/7989735 <br> ";
$a[] = "Päijät-Häme	Julkinen talous	18.10.2024		Valtuutettu ihmetteli seminaarissa: Miksi Lahti sitoo kätensä ilmoittamalla, että yt-menettelyssä pidättäydytään irtisanomisista?:	https://www.ess.fi/paikalliset/7994328 <br> ";
$a[] = "Päijät-Häme	Julkinen talous	23.10.2024		”Mahtava Excel-taulukko” oli liikaa viranhaltijoille  näin Lahti selittää 3,5 miljoonan euron virhettä kilpailutuksessa:	https://yle.fi/a/74-20119120 <br> ";
$a[] = "Päijät-Häme	Julkinen talous	25.10.2024		Maksullinen pysäköinti Lahdessa laajenee  myös asukaspysäköintialue kasvaa:	https://yle.fi/a/74-20120198 <br>";
$a[] = "Päijät-Häme	Julkinen talous	28.10.2024		Lahden tulosennuste koheni taas: alijäämä jää alle puoleen budjetoidusta:	https://www.ess.fi/paikalliset/8017392 <br>  ";





// get the q parameter from URL
$q = $_REQUEST["q"];


$hint = "";

// lookup all hints from array if $q is different from ""
if ($q !== "") {
    $q = strtolower($q);
    $len=strlen($q);
    foreach($a as $name) {
        if (stristr($q, substr($name, 0, $len))) {
            if ($hint === "") {
                $hint = $name;
            } else {
                $hint .= ", $name";
            }
        }
    }
}

// Output "no suggestion" if no hint was found or output correct values
echo $hint === "" ? "no suggestion" : $hint;
?>