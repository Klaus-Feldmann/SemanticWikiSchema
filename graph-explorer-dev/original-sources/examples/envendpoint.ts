import { createElement, ClassAttributes } from "react";
import { createRoot } from "react-dom/client";

import {
  Workspace,
  WorkspaceProps,
  SparqlDataProvider,
  SparqlQueryMethod,
  OWLRDFSSettings,
} from "../src/graph-explorer/index";

import {
  onPageLoad,
  tryLoadLayoutFromLocalStorage,
  saveLayoutToLocalStorage,
} from "./common";

const base = "https://aufeinwort.info/";
const propertyBase = `${base}prop/`;
const schemaBase = `${base}schema/`;
const entityBase = `${base}entity/`;

function onWorkspaceMounted(workspace: Workspace) {
  if (!workspace) {
    return;
  }

  workspace.getModel().importLayout({
    diagram: tryLoadLayoutFromLocalStorage(),
    validateLinks: true,

    dataProvider: new SparqlDataProvider(
      {
        endpointUrl:
          "/graph-explorer/sparql" +
          "?default-graph-uri=urn%3Ax-arq%3AUnionGraph",
        queryMethod: SparqlQueryMethod.GET,
      },
      {
        ...OWLRDFSSettings,

        classTreeQuery: `
          SELECT ?class ?label (COUNT(DISTINCT ?inst) AS ?instcount)
          WHERE {
            ?inst a ?class .
            FILTER(STRSTARTS(STR(?inst), "${entityBase}"))
            FILTER(STRSTARTS(STR(?class), "${schemaBase}"))
            BIND(
              REPLACE(STRAFTER(STR(?class), "${schemaBase}"), "_", " ")
              AS ?label
            )
          }
          GROUP BY ?class ?label
          ORDER BY ?label
        `,

        classInfoQuery: `
          SELECT ?class ?label (COUNT(DISTINCT ?inst) AS ?instcount)
          WHERE {
            VALUES (?class) { \${ids} }
            OPTIONAL { ?inst a ?class . }
            BIND(
              REPLACE(STRAFTER(STR(?class), "${schemaBase}"), "_", " ")
              AS ?label
            )
          }
          GROUP BY ?class ?label
        `,

        linkTypesQuery: `
          SELECT DISTINCT ?link ?label
          WHERE {
            ?source ?link ?target .
            FILTER(STRSTARTS(STR(?source), "${entityBase}"))
            FILTER(STRSTARTS(STR(?target), "${entityBase}"))
            FILTER(STRSTARTS(STR(?link), "${propertyBase}"))
            BIND(
              REPLACE(STRAFTER(STR(?link), "${propertyBase}"), "_", " ")
              AS ?label
            )
          }
        `,

        linkTypesInfoQuery: `
          SELECT ?link ?label WHERE {
            VALUES (?link) { \${ids} }
            BIND(
              REPLACE(STRAFTER(STR(?link), "${propertyBase}"), "_", " ")
              AS ?label
            )
          }
        `,

        propertyInfoQuery: `
          SELECT ?property ?label WHERE {
            VALUES (?property) { \${ids} }
            BIND(
              REPLACE(STRAFTER(STR(?property), "${propertyBase}"), "_", " ")
              AS ?label
            )
          }
        `,

        filterAdditionalRestriction: `
          FILTER(STRSTARTS(STR(?inst), "${entityBase}"))
        `,

        // Beziehungstypen werden aus den Daten ermittelt.
        linkConfigurations: [],
        openWorldLinks: true,

        // Kanten zwischen bereits angezeigten Subjects.
        linksInfoQuery: `
          SELECT DISTINCT ?source ?type ?target
          WHERE {
            VALUES (?source) { \${ids} }
            VALUES (?target) { \${ids} }

            ?source ?type ?target .

            FILTER(STRSTARTS(STR(?source), "${entityBase}"))
            FILTER(STRSTARTS(STR(?target), "${entityBase}"))
            FILTER(STRSTARTS(STR(?type), "${propertyBase}"))
          }
        `,

        // Eingehende und ausgehende Beziehungstypen eines Subjects.
        linkTypesOfQuery: `
          SELECT DISTINCT ?link
          WHERE {
            {
              \${elementIri} ?link ?other .
            }
            UNION
            {
              ?other ?link \${elementIri} .
            }

            FILTER(STRSTARTS(STR(?other), "${entityBase}"))
            FILTER(STRSTARTS(STR(?link), "${propertyBase}"))
          }
        `,

        // Anzahl der erreichbaren Subjects je Richtung.
        linkTypesStatisticsQuery: `
          SELECT ?link ?outCount ?inCount
          WHERE {
            {
              SELECT
                (\${linkId} AS ?link)
                (COUNT(DISTINCT ?outObject) AS ?outCount)
              WHERE {
                \${elementIri} \${linkId} ?outObject .
                FILTER(STRSTARTS(STR(?outObject), "${entityBase}"))
              }
            }
            {
              SELECT
                (\${linkId} AS ?link)
                (COUNT(DISTINCT ?inObject) AS ?inCount)
              WHERE {
                ?inObject \${linkId} \${elementIri} .
                FILTER(STRSTARTS(STR(?inObject), "${entityBase}"))
              }
            }
          }
        `,

        // Auch beim Erkunden aller Verbindungen nur
        // SWS-Eigenschaften berücksichtigen.
        filterRefElementLinkPattern: `
          FILTER(STRSTARTS(STR(?link), "${propertyBase}"))
        `,
      }
    ),
  });
}

const props: WorkspaceProps & ClassAttributes<Workspace> = {
  ref: onWorkspaceMounted,

  onSaveDiagram: (workspace) => {
    const diagram = workspace.getModel().exportLayout();
    window.location.hash = saveLayoutToLocalStorage(diagram);
    window.location.reload();
  },

  viewOptions: {
    onIriClick: ({ iri }) =>
      window.open(iri, "_blank", "noopener,noreferrer"),
  },
};

onPageLoad((container) => {
  createRoot(container).render(createElement(Workspace, props));
});