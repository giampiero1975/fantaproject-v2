<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Leagues Mapping (Football-Data API ID => FBref Competition ID)
    |--------------------------------------------------------------------------
    |
    | Qui viene definita la relazione tra gli ID ufficiali di Football-Data.org
    | (api_id) e gli ID di tracciamento e scraping utilizzati da FBref (fbref_id).
    | Se viene aggiunto un nuovo campionato estero, basta inserire una riga qui.
    |
    */
    2019 => '11', // Serie A (Italy) -> FBref ID 11 (Serie A)
    2018 => '18', // Serie B (Italy) -> FBref ID 18 (Serie B)
    2013 => '9',  // Premier League (England) -> FBref ID 9
    2014 => '12', // La Liga (Spain) -> FBref ID 12
    2015 => '20', // Ligue 1 (France) -> FBref ID 20
    2002 => '22', // Bundesliga (Germany) -> FBref ID 22
];
