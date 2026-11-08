local sws = {}
local php

function sws.setupInterface()
	sws.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	-- query is only registered on the PHP side when a Neo4j backend is configured.
	-- Drop the Lua wrapper too, so mw.sws.query is genuinely absent otherwise
	-- (rather than a function that fails one call deeper on a nil php.query).
	if not php.query then
		sws.query = nil
	end

	-- Likewise, sparqlQuery is registered only when a SPARQL store is configured.
	if not php.sparqlQuery then
		sws.sparqlQuery = nil
	end

	mw = mw or {}
	mw.sws = sws

	package.loaded['mw.sws'] = sws
end

function sws.getValue( propertyName, options )
	return php.getValue( propertyName, options )
end

function sws.getAll( propertyName, options )
	return php.getAll( propertyName, options )
end

function sws.getMainSubject( pageName )
	return php.getMainSubject( pageName )
end

function sws.getSubject( subjectId )
	return php.getSubject( subjectId )
end

function sws.getSubjects( pageName )
	return php.getSubjects( pageName )
end

function sws.query( cypher, params )
	return php.query( cypher, params )
end

function sws.sparqlQuery( sparql )
	return php.sparqlQuery( sparql )
end

function sws.getSchema( name )
	return php.getSchema( name )
end

function sws.collectFields(options)
    return php.collectFields(options)
end

function sws.hasAutomaticAnnotations()
    return php.hasAutomaticAnnotations()
end

return sws
