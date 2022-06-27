# Zenodo XML upload

Upload figures, etc. from articles in XML to Zenodo BLR.

## Elsevier

Elsevier has some content available in Open Access, although terms of license vary and some may preclude doing anything with the content (sigh). To retrieve XML for an article you need an Elsevier API key, then retrieve using DOI, e.g.:

```
curl -X GET --header 'Accept: text/xml' 'https://api.elsevier.com/content/article/doi/10.1016/j.ympev.2019.04.015?apiKey=<your API key here>=text%2Fxml'
```

For an example see https://zenodo.org/record/3610153 which is https://doi.org/10.1016/j.sajb.2017.06.021 which is available under a [CC-BY](https://creativecommons.org/licenses/by/4.0/) license.


