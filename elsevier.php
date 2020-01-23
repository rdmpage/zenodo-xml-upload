<?php

require_once(dirname(__FILE__) . '/api/api.php');

//----------------------------------------------------------------------------------------
function get($url)
{
	$data = null;
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE,
	  CURLOPT_COOKIEJAR		=> 'cookie.txt'	  
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

//----------------------------------------------------------------------------------------
// Upload a figure 
function to_zenodo($reference, $figure, $community = '')
{	
	$data = new stdclass;
	$data->metadata = new stdclass;
	
	// metadata common to a reference and a figure
	foreach ($reference as $k => $v)
	{
		switch ($k)
		{				
			case 'authors':
				$data->metadata->creators = array();
				foreach ($reference->authors as $a)
				{
					$author = new stdclass;
					$author->name = $a;
				
					$data->metadata->creators[] = $author;
				}
				break;				
		
			case 'date':
				$data->metadata->publication_date = $v;
				break;

			case 'year':
				$data->metadata->publication_date = $v . '-01-01';
				break;	
	
			default:
				break;
		}
	}
	
	// Add to a community
	if ($community != '')
	{
		$data->metadata->communities = array();
		
		$identifier = new stdclass;
		$identifier->identifier = $community;
		
		$data->metadata->communities[] = $identifier;
	}
	
	if ($figure)
	{
		// Figure
		$data->metadata->upload_type = 'image';
		$data->metadata->image_type = 'figure';
		
		foreach ($reference as $k => $v)
		{
			switch ($k)
			{									
				case 'doi':
					$data->metadata->related_identifiers = array();
					
					$related = new stdclass;
					$related->relation = 'isPartOf';
					$related->identifier = 'https://doi.org/' . strtolower($v);
					
					$data->metadata->related_identifiers[] = $related;
					break;
			
				default:
					break;
			}	
		}		
				
		$data->metadata->title = $figure->label;
		
		if (isset($reference->bibliographicCitation))
		{
			$data->metadata->title .= ' from: ' . $reference->bibliographicCitation;
		}
		
		$data->metadata->description = $figure->caption;
		
		if (isset($reference->license))
		{

			switch 	($reference->license)
			{
				case 'http://creativecommons.org/licenses/by/4.0/':
					$data->metadata->license 		= 'cc-by';
					$data->metadata->access_right 	= 'open';
					break;
			
				default:
					$data->metadata->access_right 	= 'open';
					$data->metadata->license 		= 'cc-zero';			
					break;
			}
		}
		else
		{		
			// Figures are always open and CC-0 by default
			$data->metadata->access_right 	= 'open';	
			$data->metadata->license 		= 'cc-zero';		
		}
	}
	else
	{
		// Work
		$data->metadata->upload_type = 'publication';
		$data->metadata->publication_type = 'article';
				
		foreach ($reference as $k => $v)
		{
			switch ($k)
			{
				case 'title':
					$data->metadata->{$k} = $v;
					break;

				case 'doi':
					$data->metadata->{$k} = strtolower($v);
					break;
			
				case 'abstract':
					$data->metadata->description = $v;
					break;
				
				case 'journal':
					$data->metadata->journal_title = $v;
					break;
					
				case 'volume':
					$data->metadata->journal_volume = $v;
					break;
					
				case 'issue':
					$data->metadata->journal_issue = $v;
					break;
			
				case 'spage':
					$data->metadata->journal_pages = $v;
					break;

				case 'epage':
					$data->metadata->journal_pages .= '-' . $v;
					break;

				default:
					break;
			}	
		}		
		
		// We need a description, use title if no abstract		
		if (!isset($reference->abstract))
		{
			$data->metadata->description = $data->metadata->title;
		}
		
		// License
		if (isset($reference->license))
		{

			switch 	($reference->license)
			{
				case 'http://creativecommons.org/licenses/by/4.0/':
					$data->metadata->license 		= 'cc-by';
					$data->metadata->access_right 	= 'open';
					break;
			
				default:
					$data->metadata->access_right 	= 'open';
					break;
			}
		}
		else
		{		
			// Articles are closed by default
			$data->metadata->access_right 	= 'closed';	
		}
	
	}
	
	
	return $data;
}

//----------------------------------------------------------------------------------------


$filename = 'PII-S0254629916339242.xml';

$xml = file_get_contents($filename);

$dom= new DOMDocument;
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);

$xpath->registerNamespace(   'ce', 'http://www.elsevier.com/xml/common/dtd');
$xpath->registerNamespace(   'sb', 'http://www.elsevier.com/xml/common/struct-bib/dtd');
$xpath->registerNamespace('prism', 'http://prismstandard.org/namespaces/basic/2.0/');
$xpath->registerNamespace(    'e', 'http://www.elsevier.com/xml/svapi/article/dtd');
$xpath->registerNamespace(   'dc', 'http://purl.org/dc/elements/1.1/');
$xpath->registerNamespace( 'xocs', 'ttp://www.elsevier.com/xml/xocs/dtd');


$doi = '';
$eid = '';
$license = '';

// get metadata for work

$reference = new stdclass;

$nodeCollection = $xpath->query ('//e:coredata/prism:doi');
foreach($nodeCollection as $node)
{
	$reference->doi = $node->firstChild->nodeValue;
}

$nodeCollection = $xpath->query ('//e:coredata/dc:title');
foreach($nodeCollection as $node)
{
	$reference->title = trim($node->firstChild->nodeValue);
}

$nodeCollection = $xpath->query ('//e:coredata/dc:description');
foreach($nodeCollection as $node)
{
	$reference->abstract = trim($node->firstChild->nodeValue);
	
	$reference->abstract = preg_replace('/\s\s+/u', ' ', $reference->abstract);
	$reference->abstract = preg_replace('/^Abstract\s+/u', '', $reference->abstract);
}

$nodeCollection = $xpath->query ('//e:coredata/prism:publicationName');
foreach($nodeCollection as $node)
{
	$reference->journal = $node->firstChild->nodeValue;
}

$nodeCollection = $xpath->query ('//e:coredata/prism:volume');
foreach($nodeCollection as $node)
{
	$reference->volume = $node->firstChild->nodeValue;
}

$nodeCollection = $xpath->query ('//e:coredata/prism:startingPage');
foreach($nodeCollection as $node)
{
	$reference->spage = $node->firstChild->nodeValue;
}

$nodeCollection = $xpath->query ('//e:coredata/prism:endingPage');
foreach($nodeCollection as $node)
{
	$reference->epage = $node->firstChild->nodeValue;
}

$nodeCollection = $xpath->query ('//e:coredata/prism:coverDate');
foreach($nodeCollection as $node)
{
	$reference->date = $node->firstChild->nodeValue;
}

$reference->authors = array();
$nodeCollection = $xpath->query ('//e:coredata/dc:creator');
foreach($nodeCollection as $node)
{
	$reference->authors[] = $node->firstChild->nodeValue;
}


$xpath_query = '//e:eid';
$nodeCollection = $xpath->query ($xpath_query);
foreach($nodeCollection as $node)
{
	$reference->eid = $node->firstChild->nodeValue;
}

$xpath_query = '//xocs:pii-unformatted';
$nodeCollection = $xpath->query ($xpath_query);
foreach($nodeCollection as $node)
{
	$reference->pii = $node->firstChild->nodeValue;
}

$xpath_query = '//e:openaccessUserLicense';
$nodeCollection = $xpath->query ($xpath_query);
foreach($nodeCollection as $node)
{
	$reference->license = $node->firstChild->nodeValue;
}



$terms = array();

if (isset($reference->authors))
{
	$terms[] = join('; ', $reference->authors);
}

if (isset($reference->date))
{
	$terms[] = ' (' . substr($reference->date, 0, 4) . ')';
}

if (isset($reference->title))
{
	$terms[] = ' ' . $reference->title;
}

if (isset($reference->journal))
{
	$terms[] = '. ' . $reference->journal;
}

if (isset($reference->volume))
{
	$terms[] = ':' . $reference->volume;
}

if (isset($reference->spage))
{
	$terms[] = ' ' . $reference->spage;
}

if (isset($reference->epage))
{
	$terms[] = '-' . $reference->epage;
}

if (isset($reference->doi))
{
	$terms[] = ' https://doi.org/' . $reference->doi;
}

$reference->bibliographicCitation = join('', $terms);

// PDF
// https://www.sciencedirect.com/science/article/pii/S0254629916339242/pdfft?isDTMRedir=true&download=true

$reference->pdf = 'https://www.sciencedirect.com/science/article/pii/' . $reference->pii . '/pdfft?isDTMRedir=true&download=true';

$reference->pdf_filename = $reference->pii . '.pdf';

print_r($reference);


if (!file_exists($reference->pdf_filename))
{
	$pdf = get($reference->pdf );
	file_put_contents($reference->pdf_filename, $pdf);
}


// upload reference to zenodo

if (0)
{
	$metadata = to_zenodo ($reference, null, 'biosyslit');

	print_r($metadata);

	$deposit = create_deposit();
	upload_metadata($deposit, $metadata);

	upload_file($deposit, dirname(__FILE__) . '/' . $reference->pdf_filename, $reference->pdf_filename);

	//publish($deposit);
}



// get figures

/*
         <ce:floats>
            <ce:figure id="f0005">
               <ce:label>Fig. 1</ce:label>
               <ce:caption id="ca0005">
                  <ce:simple-para id="sp0005" view="all">Variation in the corolla and corona in the traditional concept of <ce:italic>Ceropegia</ce:italic>: A–C, <ce:italic>C. salicifolia</ce:italic>, Nepal, <ce:italic>Bruyns 2507</ce:italic> (BM, K); D–E, <ce:italic>C. melanops</ce:italic>, Ethiopia, <ce:italic>Gilbert 3050</ce:italic> (K); F—H, <ce:italic>C. meleagris</ce:italic>, Nepal, <ce:italic>Bruyns 2496</ce:italic> (K); I–J, <ce:italic>C. loranthiflora</ce:italic>, Ethiopia, <ce:italic>Gilbert 2851</ce:italic> (K). [scale-bars or subdivisions indicate mm; A, D, F, I, corolla from side; B, G, corolla dissected to show location of corona; C, E, H, J, corona from side].</ce:simple-para>
               </ce:caption>
               <ce:alt-text role="short" id="al0005">Fig. 1</ce:alt-text>
               <ce:link id="lk0005" locator="gr1" xlink:type="simple" xlink:href="pii:S0254629916339242/gr1" xlink:role="http://data.elsevier.com/vocabulary/ElsevierContentTypes/23.4"/>
            </ce:figure>
*/

$xpath_query = '//ce:floats/ce:figure';
$nodeCollection = $xpath->query ($xpath_query);
foreach($nodeCollection as $node)
{
	if ($node->hasAttributes()) 
	{ 
		$attributes = array();
		$attrs = $node->attributes; 
		
		foreach ($attrs as $i => $attr)
		{
			$attributes[$attr->name] = $attr->value; 
		}
		
		$key = $attributes['id'];
	}
    // figure
    
    $figure = new stdclass;
    
    
    $nc = $xpath->query ('ce:label', $node);
	foreach($nc as $n)
	{
		$figure->label = $n->firstChild->nodeValue;
	}

    $nc = $xpath->query ('ce:caption', $node);
	foreach($nc as $n)
	{
		$figure->caption = $n->nodeValue;
		$figure->caption = preg_replace('/\s\s+/u', ' ', $figure->caption);
	}

    $nc = $xpath->query ('ce:link/@locator', $node);
	foreach($nc as $n)
	{		
		$figure->filename = $reference->eid . '-' . $n->nodeValue . '_lrg.jpg';
		$figure->url = 'https://ars.els-cdn.com/content/image/' . $figure->filename;
		
		if (!file_exists($figure->filename))
		{
			$image = get($figure->url);
			file_put_contents($figure->filename, $image);
		}
		
	}
	
	
	
	//print_r($figure);
	
	if (0)
	{
	
		$metadata = to_zenodo ($reference, $figure, 'biosyslit');
	
		print_r($metadata);
	
		$deposit = create_deposit();
		upload_metadata($deposit, $metadata);
	
		upload_file($deposit, dirname(__FILE__) . '/' . $figure->filename, $figure->filename);
		
		//publish($deposit);
	}	
	
    
}




?>
