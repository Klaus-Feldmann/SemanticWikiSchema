import{createSchemaQueries as t}from"./sws-qids-51a894f52603.js";const i=t(globalThis.SWSGraphConfig.baseIri,document.documentElement.lang||navigator.language||"de");import{i as o,n as l,o as R,r as a,s as E,t as s,u as I,v as u,x as c,y as L}from"./common-qids-d57208f51919.js";var p=c(L()),k=u(),S=globalThis.SWSGraphConfig.baseIri,n=`${S}prop/`,y=`${S}schema/`,T=`${S}entity/`;function A(e){e&&e.getModel().importLayout({diagram:a(),validateLinks:!0,dataProvider:new R({endpointUrl:globalThis.SWSGraphConfig.sparqlUrl,queryMethod:E.GET},{...I,classTreeQuery:i.classTreeQuery,classInfoQuery:i.classInfoQuery,linkTypesQuery:`
          SELECT DISTINCT ?link ?label
          WHERE {
            ?source ?link ?target .
            FILTER(STRSTARTS(STR(?source), "${T}"))
            FILTER(STRSTARTS(STR(?target), "${T}"))
            FILTER(STRSTARTS(STR(?link), "${n}"))
            BIND(
              REPLACE(STRAFTER(STR(?link), "${n}"), "_", " ")
              AS ?label
            )
          }
        `,linkTypesInfoQuery:`
          SELECT ?link ?label WHERE {
            VALUES (?link) { \${ids} }
            BIND(
              REPLACE(STRAFTER(STR(?link), "${n}"), "_", " ")
              AS ?label
            )
          }
        `,propertyInfoQuery:`
          SELECT ?property ?label WHERE {
            VALUES (?property) { \${ids} }
            BIND(
              REPLACE(STRAFTER(STR(?property), "${n}"), "_", " ")
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
            FILTER(STRSTARTS(STR(?type), "${n}"))
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
            FILTER(STRSTARTS(STR(?link), "${n}"))
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
          FILTER(STRSTARTS(STR(?link), "${n}"))
        `})})}var $={ref:A,onSaveDiagram:e=>{let r=e.getModel().exportLayout();window.location.hash=l(r),window.location.reload()},viewOptions:{onIriClick:({iri:e})=>window.open(e,"_blank","noopener,noreferrer")}};s(e=>{(0,k.createRoot)(e).render((0,p.createElement)(o,$))});

//# sourceMappingURL=envendpoint-qids-5d05705fa345.js.map
