
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

---

## 🤖 AI Tulevaisuusluotain Dashboard - Painikkeiden Toiminnot

AI-hallintapaneeli (`src/Ai/ai_dashboard.html`) sisältää useita painikkeita, jotka suorittavat erilaisia tehtäviä. Tässä on kattava selostus kunkin painikkeen toiminnasta:

### 📊 **Uutisten Keruu ja Tallennus**

#### 💾 **Kerää Tietokantaan** (Collect to Database)
- **Toiminta**: Kerää uutisartikkeleita useista RSS-lähteistä ja tallentaa ne MySQL-tietokantaan
- **Tietokanta**: Tallentaa `news_articles` tauluun
- **OpenAI**: Ei käytä (vain tiedonkeruu)
- **Lähteet**: YLE Häme, Hämeen Sanomat, STT
- **Tiedosto**: `database_news_collector.php`
- **Mitä tapahtuu**: 
  - Hakee RSS-syötteet
  - Suodattaa Häme-alueen relevantit uutiset
  - Tallentaa uudet artikkelit tietokantaan
  - Välttää duplikaatteja

#### ✅ **Toimivuustesti** (Working Test)  
- **Toiminta**: Testaa RSS-yhteydet ja uutisten keräämisen ilman tietokantaan tallentamista
- **Tietokanta**: Ei tallenna (vain testaus)
- **OpenAI**: Ei käytä
- **Tiedosto**: `news_working_test.php`
- **Mitä tapahtuu**: Näyttää löytyvät uutiset reaaliajassa ilman tallentamista

#### 📰 **Näytä Tallennetut** (View Stored News)
- **Toiminta**: Näyttää viimeisimmät tallennetut uutisartikkelit tietokannasta
- **Tietokanta**: Lukee `news_articles` taulusta
- **OpenAI**: Ei käytä
- **Tiedosto**: `database_news_collector.php` (action=recent)
- **Mitä tapahtuu**: Näyttää 10 viimeisintä artikkelia ja niiden metatiedot

### 🧠 **AI-Analyysi Toiminnot**

#### 🤖 **Analysoi Artikkeleita** (Analyze Articles)
- **Toiminta**: Analysoi tallennettuja uutisartikkeleita OpenAI:lla erä kerrallaan
- **Tietokanta**: Lukee `news_articles`, päivittää analyysikenttiin tulokset
- **OpenAI**: ✅ **KYLLÄ** - Käyttää GPT-3.5-turbo API:a
- **Tiedosto**: `database_news_collector.php` (action=analyze)
- **Mitä tapahtuu**:
  - Hakee analysoimattomat artikkelit (5 kpl kerrallaan)
  - Lähettää OpenAI:lle analyysin tilausketjun
  - Saa takaisin: sentimentti, avainsanat, tiivistelmä, relevanssi
  - Tallentaa tulokset `ai_*` kenttiin
  - **KUSTANNUS**: ~$0.01-0.03 per artikkeli riippuen pituudesta

#### 🧠 **Suorita AI-analyysi** (Run AI Analysis)
- **Toiminta**: Päivittää dashboard-visualisoinnit ja käynnistää yleisen analyysin
- **Tietokanta**: Lukee analysoidut tulokset
- **OpenAI**: Ei käytä (käyttää valmista dataa)
- **Tiedosto**: JavaScript-funktio (`runAnalysis()`)
- **Mitä tapahtuu**: Päivittää kaaviot ja mittarit tallennetusta datasta

### 📰 **Mediaseuranta AI-Analyysi**

#### 📰 **Analysoi Mediaseuranta** (Analyze Mediaseuranta)
- **Toiminta**: Analysoi Mediaseuranta-taulun sisältöä OpenAI:lla
- **Tietokanta**: Lukee `Mediaseuranta` taulusta, tallentaa AI-tulokset
- **OpenAI**: ✅ **KYLLÄ** - Käyttää GPT-3.5-turbo API:a  
- **Tiedosto**: `mediaseuranta_analyzer.php` (action=analyze)
- **Mitä tapahtuu**:
  - Hakee analysoimattomat Mediaseuranta-merkinnät
  - Lähettää OpenAI:lle analyysin (relevanssi, toimialat, sentimentti)
  - Tallentaa tulokset AI-sarakkeisiin (`ai_relevance_score`, `ai_key_sectors`, jne.)
  - **KUSTANNUS**: ~$0.02-0.05 per merkintä

#### 📊 **Mediaseurannan Tulokset** (View Mediaseuranta Insights)
- **Toiminta**: Näyttää Mediaseuranta AI-analyysien tulokset
- **Tietokanta**: Lukee analysoidut tulokset `Mediaseuranta` taulusta
- **OpenAI**: Ei käytä (näyttää valmista dataa)
- **Tiedosto**: `mediaseuranta_analyzer.php` (action=insights)
- **Mitä tapahtuu**: Näyttää analyysin tulokset: relevanssi, toimialat, tunnelma

### 🔧 **Debugging ja Testaus**

#### 🔧 **Debuggaa Mediaseuranta** (Debug Mediaseuranta)
- **Toiminta**: Tarkistaa Mediaseuranta-taulun rakenteen ja yhteyden
- **Tietokanta**: Tarkistaa taulun olemassaolon ja AI-sarakkeet
- **OpenAI**: Ei käytä
- **Tiedosto**: `mediaseuranta_analyzer.php` (action=debug)
- **Mitä tapahtuu**: Diagnose-toiminto ongelmien selvittämiseen

#### 🧪 **Testaa Dataa** (Test Data)
- **Toiminta**: Testaa datan hakemista Mediaseuranta-taulusta
- **Tietokanta**: Lukee muutamia esimerkkimerkintöjä
- **OpenAI**: Ei käytä  
- **Tiedosto**: `mediaseuranta_analyzer.php` (action=test)
- **Mitä tapahtuu**: Näyttää esimerkkidata ja taulun tilan

### 🚨 **Analysointi ja Raportit**

#### 🚨 **Tarkista Hälytykset** (Check Alerts)
- **Toiminta**: Etsii kriittisiä signaaleja analysoiduista uutisista
- **Tietokanta**: Lukee AI-analyysituloksia
- **OpenAI**: Ei käytä (käyttää sääntöpohjaista analyysia)
- **Tiedosto**: `news_intelligence_api.php` (action=alerts)
- **Mitä tapahtuu**: Tunnistaa korkean vaikutuksen tapahtumat ja kriisit

#### 📊 **Viikkoraportti** (Weekly Report)
- **Toiminta**: Luo viikkoinen yhteenveto AI-analyyseista
- **Tietokanta**: Lukee viikon AI-tulokset
- **OpenAI**: Ei käytä (koostaa valmista dataa)
- **Tiedosto**: `news_intelligence_api.php` (action=weekly_report)
- **Mitä tapahtuu**: Generoi raportin trendeistä ja merkittävistä tapahtumista

#### 🔍 **Kilpailutiedustelu** (Competitive Intelligence)  
- **Toiminta**: Analysoi yritysten ja markkinoiden toimintaa
- **Tietokanta**: Lukee AI-analyysituloksia
- **OpenAI**: Ei käytä (käyttää sääntöpohjaista analyysia)
- **Tiedosto**: `news_intelligence_api.php` (action=competitive_intelligence)
- **Mitä tapahtuu**: Tunnistaa yritysmaininnat ja markkinaliikkeet

---

### 💰 **OpenAI Kustannukset ja Optimointi**

**Maksetut OpenAI API kutsut:**
- ✅ **Analysoi Artikkeleita**: ~$0.01-0.03 per artikkeli
- ✅ **Analysoi Mediaseuranta**: ~$0.02-0.05 per merkintä

**Ilmaiset toiminnot (ei OpenAI kustannuksia):**
- Kerää Tietokantaan, Toimivuustesti, Näytä Tallennetut
- Debuggaa, Testaa Dataa, Tarkista Hälytykset, Viikkoraportti, Kilpailutiedustelu

**Kustannusten hallinta:**
- Token-raja: 800 tokenia per pyyntö
- Eräkoko: 5 artikkelia kerrallaan
- Estimoitu kuukausikustannus: $5-15 (riippuen käytöstä)

### 📋 **Tietokanta Schema**

**news_articles** (uutisartikkelit):
- Perustieto: title, content, url, source, published_date
- AI-analyysi: ai_summary, ai_keywords, ai_sentiment, ai_relevance_score

**Mediaseuranta** (mediaseurantamerkinnät): 
- Perustieto: Uutinen, Teema, Maakunta_Nimi, uutisen_pvm
- AI-analyysi: ai_summary, ai_keywords, ai_sentiment, ai_relevance_score, ai_key_sectors, ai_economic_impact

Järjestelmä on suunniteltu kustannustehokkaaseen ja skaalautuvaan AI-analyysiin!

