import{i,n as t,o as E,r as a,s as r,t as s,u as o,v as I,x as A,y as L}from"./common-qids-ff085d6ffe9a.js";var c=A(L()),$=I(),R=globalThis.SWSGraphConfig.baseIri,S=`${R}prop/`,l=`${R}schema/`,T=`${R}entity/`;function C(e){e&&e.getModel().importLayout({diagram:a(),validateLinks:!0,dataProvider:new E({endpointUrl:globalThis.SWSGraphConfig.sparqlUrl,queryMethod:r.GET},{...o,classTreeQuery:`
          SELECT ?class ?label (COUNT(DISTINCT ?inst) AS ?instcount)
          WHERE {
            ?inst a ?class .
            FILTER(STRSTARTS(STR(?inst), "${T}"))
            FILTER(STRSTARTS(STR(?class), "${l}"))
            BIND(
              REPLACE(STRAFTER(STR(?class), "${l}"), "_", " ")
              AS ?label
            )
          }
          GROUP BY ?class ?label
          ORDER BY ?label
        `,classInfoQuery:`
          SELECT ?class ?label (COUNT(DISTINCT ?inst) AS ?instcount)
          WHERE {
            VALUES (?class) { \${ids} }
            OPTIONAL { ?inst a ?class . }
            BIND(
              REPLACE(STRAFTER(STR(?class), "${l}"), "_", " ")
              AS ?label
            )
          }
          GROUP BY ?class ?label
        `,linkTypesQuery:`
          SELECT DISTINCT ?link ?label
          WHERE {
            ?source ?link ?target .
            FILTER(STRSTARTS(STR(?source), "${T}"))
            FILTER(STRSTARTS(STR(?target), "${T}"))
            FILTER(STRSTARTS(STR(?link), "${S}"))
            BIND(
              REPLACE(STRAFTER(STR(?link), "${S}"), "_", " ")
              AS ?label
            )
          }
        `,linkTypesInfoQuery:`
          SELECT ?link ?label WHERE {
            VALUES (?link) { \${ids} }
            BIND(
              REPLACE(STRAFTER(STR(?link), "${S}"), "_", " ")
              AS ?label
            )
          }
        `,propertyInfoQuery:`
          SELECT ?property ?label WHERE {
            VALUES (?property) { \${ids} }
            BIND(
              REPLACE(STRAFTER(STR(?property), "${S}"), "_", " ")
              AS ?label
            )
          }
        `,filterAdditionalRestriction:`
          FILTER(STRSTARTS(STR(?inst), "${T}"))
        `,linkConfigurations:[],openWorldLinks:!0,linksInfoQuery:`
          SELECT DISTINCT ?source ?type ?target
          WHERE {
            VALUES (?source) { \${ids} }
            VALUES (?target) { \${ids} }

            ?source ?type ?target .

            FILTER(STRSTARTS(STR(?source), "${T}"))
            FILTER(STRSTARTS(STR(?target), "${T}"))
            FILTER(STRSTARTS(STR(?type), "${S}"))
          }
        `,linkTypesOfQuery:`
          SELECT DISTINCT ?link
          WHERE {
            {
              \${elementIri} ?link ?other .
            }
            UNION
            {
              ?other ?link \${elementIri} .
            }

            FILTER(STRSTARTS(STR(?other), "${T}"))
            FILTER(STRSTARTS(STR(?link), "${S}"))
          }
        `,linkTypesStatisticsQuery:`
          SELECT ?link ?outCount ?inCount
          WHERE {
            {
              SELECT
                (\${linkId} AS ?link)
                (COUNT(DISTINCT ?outObject) AS ?outCount)
              WHERE {
                \${elementIri} \${linkId} ?outObject .
                FILTER(STRSTARTS(STR(?outObject), "${T}"))
              }
            }
            {
              SELECT
                (\${linkId} AS ?link)
                (COUNT(DISTINCT ?inObject) AS ?inCount)
              WHERE {
                ?inObject \${linkId} \${elementIri} .
                FILTER(STRSTARTS(STR(?inObject), "${T}"))
              }
            }
          }
        `,filterRefElementLinkPattern:`
          FILTER(STRSTARTS(STR(?link), "${S}"))
        `})})}var u={ref:C,onSaveDiagram:e=>{let n=e.getModel().exportLayout();window.location.hash=t(n),window.location.reload()},viewOptions:{onIriClick:({iri:e})=>window.open(e,"_blank","noopener,noreferrer")}};s(e=>{(0,$.createRoot)(e).render((0,c.createElement)(i,u))});

//# sourceMappingURL=envendpoint-qids-d5fb85b7c35a.js.map
