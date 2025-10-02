
# Hämeen osaamistarpeiden ennakointi - Ennakointialusta

Tämä projekti on Hämeen osaamistarpeiden ennakointialusta, joka tarjoaa tilannekuvaa ja tulevaisuuskuvaa alueen koulutuksesta, työllisyydestä, väestöstä ja elinvoimasta. Sivusto hyödyntää PHP-backendia ja dynaamista HTML/JS-frontendia. Tämän ohjelman scriptit hakee tietokannasta tiedot.

Yhteyden ulkoisiin tilastoihin hoidetaan Backend ohjelmilla (projekti: Ennakointi-node-rest)

## Pääasialliset tiedostot ja kansiot. Täsmällinen toiminta löytyy tiedostojen kommenteista.

- **index.html**  
  Sivuston etusivu. Sisältää esittelyn, navigaation ja linkit tilannekuvaan, tulevaisuuskuvaan sekä info-sivulle.

- **opiskelu.html**  
  Koulutuksen tilastot ja visualisoinnit. Näyttää dynaamisesti koulutustilastot (toisen asteen ja korkea-asteen suorittaneet) Päijät-Hämeessä, Kanta-Hämeessä ja koko maassa.

- **tyollisyys.html**  
  Työllisyystilastot ja visualisoinnit. Näyttää dynaamisesti työttömien osuudet, avoimet työpaikat ja työttömät työnhakijat.

- **aluekehitys.html, vaesto.html, toimialaennakointi.html, analyysit.html, mediaseuranta.html, info.html**  
  Muut tilannekuvan ja ennakoinnin osiot.

- **opiskelu.php**  
  Backend-PHP, joka hakee koulutustilastot tietokannasta ja palauttaa ne JSON-muodossa frontendille.

- **tyollisyys.php**  
  Backend-PHP, joka hakee työllisyystilastot tietokannasta ja palauttaa ne JSON-muodossa frontendille.
  Asennetaan palvelimen /cgi-bin -kansioon ja ajastetaan linuxin crontab ajamaan tämä vaikka kerran päivässä 

- **haeMaakunnalla.php, haehakusanalla.php, haehakusanalla sql-server.php, sqltest sql-server.php**  
  Erilaisia PHP-skriptejä tiedonhakuun ja hakutoimintoihin. Käytetään mediaseurannat tietojen hakuun kannasta.

- **header.html, footer.html, otsikot.html**  
  Sivuston yhteiset osat (ylä- ja alatunnisteet, otsikot).

- **styles.css, signaalit.css, webflow.css, caroucell/style.css, caroucell/styles.css**  
  Tyylitiedostot.

- **webflow.js, caroucell/script.js**  
  JavaScript-tiedostot dynaamisiin toimintoihin ja visualisointeihin.

- **img/**  
  Kuvakansio (logot, taustakuvat, visualisoinnit).

- **SQL/**  
  Tietokannan mediaseurannan varmuuskopiot ja rakenteet (.sql-tiedostot).

- **react/**  
  Mahdolliset React-komponentit tai kehityskokeilut.

## Käyttö

1. Avaa `index.html` selaimessa.
2. Navigoi tilannekuva- ja tulevaisuuskuva-osiin.
3. Dynaamiset tilastot päivittyvät automaattisesti PHP-backendin kautta.

## Yhteystiedot ja palaute

Palautetta ja kehitysehdotuksia voi lähettää osoitteeseen info@tulevaisuusluotain.fi.






## Vaatimukset
- PHP (vähintään 7.x)
- MySQL-tietokanta
- Oikeat tietokantataulut ja -rakenne (katso kunkin skriptin kommentit)

## Kehitysympäristön ja versionhallinnan työkalut

- **GitHub Desktop**: Helppo graafinen käyttöliittymä versionhallintaan ja projektin synkronointiin GitHubiin.
- **Visual Studio Code (VS Code)**: Suositeltu editori PHP/Node.js-kehitykseen, tukee mm. etäyhteyksiä ja versionhallintaa.
- **SSH-yhteys palvelimelle**: Tarvitset SSH-yhteyden (esim. PuTTY, OpenSSH, VS Code Remote SSH) ohjelmien siirtoon ja ajamiseen palvelimella.
- **Palvelimella** phpMyAdmin: Tietokannan hallintaan ja tarkasteluun.
- **Linux-palvelin Domaintohelli** : Ajastettu ajo (crontab) ja PHP-ohjelmien suoritus.

### Esimerkkityökalujen asennus
- [GitHub Desktop](https://desktop.github.com/)
- [Visual Studio Code](https://code.visualstudio.com/)
- [PuTTY (Windows SSH)](https://www.putty.org/)

### Vinkit
- Pidä projektin tiedostot versionhallinnassa (GitHub), jotta muutokset ja varmuuskopiot säilyvät.
- Testaa skriptit ensin paikallisesti, ennen kuin ajastat ne tuotantopalvelimelle.

