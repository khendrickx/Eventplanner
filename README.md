# Race planner
This laravel application provides organizers a graphical way to plan their event. It is inspired and copies features from https://www.oneplan.io/features/.

## Maps
Application is generic and supports various layers, such as open street maps (via https://openfreemap.org) and Vlaanderen satelite imagery (data set: https://www.vlaanderen.be/datavindplaats/catalogus/orthofotomozaiek-middenschalig-winteropnamen-kleur-2025-vlaanderen. Example request: https://geo.api.vlaanderen.be/OMW/wmts?layer=omwrgb25vl&style=&tilematrixset=BPL2008VL&Service=WMTS&Request=GetTile&Version=1.0.0&Format=image%2Fpng&TileMatrix=12&TileCol=2508&TileRow=1776). 


## Authentication
Users can login and create, edit and delete events. For each event, a user can invite another user to collaborate, hence giving access to that event.