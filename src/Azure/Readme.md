# Azure-silta: api_stub.php ja sync_from_azure_http.php

Tämä ohje kuvaa, miten `api_stub.php` (Azure App Servicessä) ja `sync_from_azure_http.php` (palvelimellasi) yhdessä toteuttavat ei-blokkaavan synkronoinnin Azure SQL -tietokannasta MySQL-tietokantaan.

## Yleiskuva
- `MediaseurantaQueue`-tauluun kirjataan jonotustietue, kun uusi rivi syntyy `Mediaseuranta`-tauluun (Power Automaten prosessin tai muun lisäyksen seurauksena). Jonotus on ei-blokkaava.
- `api_stub.php` tarjoaa HTTP-rajapinnan Azure App Servicessä:
	- `action=get_queue`: palauttaa käsittelemättömät jonotustietueet (status='pending') ja niihin liittyvät `Mediaseuranta`-kentät.
	- `action=mark_processed`: merkitsee annetut `queue_id`-arvot käsitellyksi (status='processed') ja päivittää `last_attempt`.
	- Autentikointi: `api_key` annetaan kyselyparametrina (GET) tai lomakerunkona (POST). Käytössä on avain `your-secret-api-key`.
- `sync_from_azure_http.php` ajaa palvelimellasi ajastettuna (cron) tai käsin:
	- Hakee jonon `api_stub.php?action=get_queue&api_key=...`-kutsulla.
	- Lisää rivit MySQL:n `Mediaseuranta`-tauluun.
	- Kutsuu `api_stub.php?action=mark_processed&queue_ids=...&api_key=...` merkitäkseen käsitellyt `queue_id`:t.

## Rajapinnan sijainti ja kutsut
- Azure-sovellus: `https://<APP_NAME>.azurewebsites.net/api_stub.php`
- Esimerkkikutsut (PowerShell):
	- Haku (GET):
		`Invoke-WebRequest -Uri "https://<APP_NAME>.azurewebsites.net/api_stub.php?action=get_queue&api_key=your-secret-api-key" -Method GET -UseBasicParsing`
	- Merkintä käsitellyksi (GET):
		`Invoke-WebRequest -Uri "https://<APP_NAME>.azurewebsites.net/api_stub.php?action=mark_processed&queue_ids=1,53&api_key=your-secret-api-key" -Method GET -UseBasicParsing`

Huom: GET on tällä hetkellä vakaa sekä haussa että merkinnässä. POST toimii osittain, mutta ympäristön reititys saattaa ajoittain palauttaa 404, joten käytämme GETiä.

## Tietomallit
- Jonotaulu `MediaseurantaQueue`:
	- `queue_id`, `record_id`, `created_at`, `status`, `retry_count`, `last_attempt`, `error_message`
	- Haku suodattaa `status='pending'` ja järjestää `created_at` mukaan.
- `Mediaseuranta`-taulu: API palauttaa laajat kentät (mm. AI- ja kilpailuanalyysit), mutta synkronointi voi lisätä vain tarvitut kentät MySQL:ään. Laajennus on mahdollista myöhemmin.

## Ajastus (cron, Linux)
Lisää ajastettu ajo, esim. 5 minuutin välein:

```
*/5 * * * * /usr/bin/php /polku/projektiin/src/sync_from_azure_http.php >> /var/log/azure_sync.log 2>&1
```

## Vianmääritys
- Jos saat 404-virheitä POST-kutsuissa, käytä GETiä.
- Varmista, että `api_stub.php` sijaitsee Azure App Servicen web-juuressa (`/site/wwwroot`).
- Tarvittaessa käynnistä App Service uudelleen Azure Portaalista.

## Yhteenveto
- `api_stub.php` toimii Azure SQL → HTTP -siltana ja tarjoaa jonon haun ja käsittelyn.
- `sync_from_azure_http.php` hakee, vie MySQL:ään ja kuittaa käsitellyksi.
- Toteutus on ei-blokkaava, kevyt ja toimii ilman SQL Server -ajureita palvelimella, koska Azure SQL -yhteys on rajapinnan sisällä.

