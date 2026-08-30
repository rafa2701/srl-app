# SRL Sporting Regulations & Virtual Racing Etiquette Design Specification

## 1. Executive Summary
This design defines the conversion of the official SRL Sporting Regulations from PDF (`Reglamento Deportivo SIM RACING LATINOAMERICA (6).pdf`) into clean, structured Markdown, along with the formulation of a comprehensive Virtual Racing Etiquette framework based on international sim racing standards (FIA ISC Appendix L, iRacing Sporting Code, SRO Esports).

The output provides three modular markdown documents to support both website publication and AI Commissary evaluation via n8n and WordPress REST API.

## 2. Architecture & File Structure

The documentation will be split into three files located in `docs/`:

1. `docs/reglamento-deportivo-srl.md`
   - **Type:** Official League Regulations (1:1 Markdown replica of PDF).
   - **Scope:** 24 Chapters, 45 Articles, and Complete Sanctions Tables (R01–R02, T01, A01–A03, E01, P01–P05, C01–C31, S01–S03).
   - **Purpose:** Official sporting reference for human drivers and administrators on the website.

2. `docs/codigo-etiqueta-carreras.md`
   - **Type:** Driving Standards & Sim Racing Stewarding Guidelines.
   - **Scope:** Technical racing criteria including Vortex of Danger, Corner Rights / Overlap at Turn-in & Apex, Moving under Braking, Safe Rejoins, Blue Flag Protocols, and Burden of Proof.
   - **Purpose:** Objective judging framework for racing incident analysis.

3. `docs/reglamento-completo-srl.md`
   - **Type:** Unified Master Rulebook.
   - **Scope:** Direct combination of `reglamento-deportivo-srl.md` followed by `codigo-etiqueta-carreras.md` as Appendix / Extended Guidelines.
   - **Purpose:** Single-source text loaded into WordPress option `srl_rulebook_markdown` and served via `GET /wp-json/srl/v1/rulebook` for the n8n AI Commissary workflows.

## 3. Detailed Specifications

### 3.1 `docs/reglamento-deportivo-srl.md`
Will contain complete, unabridged articles:
- **Capítulo I – Disposiciones Generales:** Art. 1 (Lectura obligatoria).
- **Capítulo II – Servidores Oficiales:** Art. 2 (Servidores dedicados 24/7), Art. 3 (KVR NEW para F1 y Porsche GT3), Art. 4 (Cambios por contingencia vía WhatsApp).
- **Capítulo III – Sesiones de Práctica:** Art. 5 (Práctica extraoficial), Art. 6 (Práctica oficial 1h antes de clasificación + tabla de horarios Arg/Bra/Par 21:30/22:00, Bol/Chi 21:30/22:00, Vzla 20:30/21:00, Col/Ecu/Per 19:30/20:00).
- **Capítulo IV – Clasificación y Carrera:** Art. 7 (Clasificación 15 min, vuelta lanzada), Art. 8 (Salida en parada, puntualidad), Art. 9 (Salida del servidor en clasificación).
- **Capítulo V – Campeonatos y Sistema de Puntuación:** Art. 10 (Condiciones de carrera), Art. 11 (Criterios de desempate por primeros lugares, segundos, etc.), Art. 12 (Mínimo 7 pilotos para validez).
- **Capítulo VI – Conducta y Comunicación:** Art. 13 (Comportamiento y prohibición de chat del simulador), Art. 14 (Sanciones por chat en quali/carrera), Art. 15 (Uso indebido de redes sociales de la liga), Art. 16 (Chat del canal de transmisión).
- **Capítulo VII – Reinicio de Eventos:** Art. 17 (Causales de reinicio: caída temporal o kick masivo >=50%).
- **Capítulo VIII – Suspensión o Invalidación de Resultados:** Art. 18 (Caída absoluta de servidor).
- **Capítulo IX – Reclamos:** Art. 19 (Ventana hasta el día siguiente a las 23:59 Vzla / 22:59 Col / 00:59 Arg; formato de denuncia y enlace al formulario Google oficial).
- **Capítulo X – Penalizaciones de Oficio:** Art. 20 (Revisión de oficio de maniobras graves y sector 1 de la vuelta 1).
- **Capítulo XI – Comportamiento en Pista:** Art. 21 (Límites de pista y uso de pianos), Art. 22 (Maniobras peligrosas, 1 solo cambio de dirección, divebomb, break check), Art. 23 (Pilotos rezagados y bandera azul).
- **Capítulo XII – Incidentes y Sanciones:** Art. 24 (Definición de incidentes), Art. 25 (Clasificación de sanciones R, T, A, E, P, C, S).
- **Capítulo XIII – Comportamiento en Pista y Penalizaciones:** Art. 26 (R01, R02, T01), Art. 27 (A01, A02, A03), Art. 28 (Origen de sanciones), Art. 29 (Incidentes en prácticas), Art. 30 (Incidentes en clasificación).
- **Sanciones en Prácticas y Clasificación:** Art. 31 (E01), Art. 32 (P01–P05).
- **Capítulo XIV – Conducta en Carrera y Penalizaciones:** Art. 33 (Definición de Puntero), Art. 34 (Reglas de adelantamiento por doblaje - máx 3 curvas o recta).
- **Sanciones en Carrera:** Art. 35 (C01–C14).
- **Capítulo XV – Marcha Atrás en la Grilla:** Art. 36 (Prohibición y C15).
- **Capítulo XVI – Bloqueo de Pista, Trazadas y Comportamiento Estratégico:** Art. 37 (C16), Art. 38 (C17).
- **Capítulo XVII – Derechos en Curvas y Sobrepaso:** Art. 39 (Límites en curvas), Art. 40 (Sobrepaso válido: frontal más allá de la mitad antes del cruce), Art. 41 (Derecho a carril exterior/interior).
- **Capítulo XVIII – Incidentes en Boxes:** C18, C19, C20.
- **Capítulo XIX – Penalizaciones del Juego y Paradas:** C21, C22.
- **Capítulo XX – Cortes de Pista:** Art. 42 (Máximo 3 cortes, luego DT).
- **Capítulo XXI – Otros Incidentes en Carrera:** Art. 43–45 (Sentido contrario y reversa).
- **Capítulo XXII – Post-Carrera y Puntos:** C23–C26 (Bandera amarilla, etc.).
- **Capítulo XXIII – Frenadas y Movimientos Antideportivos:** C27–C31.
- **Capítulo XXIV – Atribuciones de la Administración:** S01–S03.

### 3.2 `docs/codigo-etiqueta-carreras.md`
Technical driving standards structured into 6 core sections:
1. **Concepto y Geometría del "Vórtice de Peligro" (*Vortex of Danger*):**
   - Vórtice ciego interior y exterior.
   - Determinación de culpabilidad cuando el atacante introduce el vehículo en un espacio que desaparece (*closing window*).
2. **Criterios de Solapamiento y Propiedad de la Curva (*Corner Rights*):**
   - Requisitos métricos para exigir espacio: Eje delantero a la altura de retrovisores/eje delantero antes del inicio de la fase de frenada o giro.
   - Obligación del defensor de dejar al menos 1 ancho de coche (*racing room*).
   - Maniobras por el exterior: Exigencia de estar a la par o por delante en el punto de vértice (*apex*).
3. **Comportamiento en Zona de Frenada (*Braking Zone Etiquette*):**
   - Prohibición absoluta de *moving under braking* o cambios de trayectoria reactivos.
   - Definición de maniobra de defensa única y retorno dejando espacio reglamentario.
4. **Protocolo Estricto de Reincorporación a Pista (*Safe Rejoin Guidelines*):**
   - Pérdida total de prioridad tras despiste o trompo.
   - Obligación de mantener frenos bloqueados durante trompos incontrolados (*holding the brakes*).
   - Reincorporación paralela a la línea blanca, fuera de la trazada ideal y verificando tráfico relativo/radar.
5. **Comportamiento ante Banderas Azules y Rezagados:**
   - Procedimiento predecible para ceder el paso: mantener línea y levantar el acelerador (*lift and coast*) en zona recta sin frenadas intempestivas.
6. **Estándares de Evaluación para Comisarios e Inteligencia Artificial:**
   - Principio del "Culpable Predominante" (*Predominantly to Blame*).
   - Regla de Incidente de Carrera (*Racing Incident*) ante falta de dolo o circunstancias imprevisibles.
   - Carga de la prueba (*Burden of Proof*) y desestimación por insuficiencia probatoria.

### 3.3 `docs/reglamento-completo-srl.md`
- Master markdown file merging both documents.
- Includes a table of contents and clear headings for easy navigation on the web and seamless AI consumption.

## 4. Verification Plan
- **Verification of Articles:** Cross-check all 24 chapters, 45 articles, and sanction codes (R01-R02, T01, A01-A03, E01, P01-P05, C01-C31, S01-S03) against the source PDF to ensure 100% fidelity.
- **Markdown Linting & Formatting:** Ensure clean GitHub-flavored markdown with consistent tables, lists, and headings.
- **REST API Compatibility:** Verify that the merged markdown loads correctly in WordPress and is accessible via `GET /wp-json/srl/v1/rulebook`.
