# 🏎️ SRL League System

[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue.svg)](https://wordpress.org/)
[![Version](https://img.shields.io/badge/version-1.9.2-red.svg)](./)
[![License](https://img.shields.io/badge/license-GPL--2.0-lightgrey.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A comprehensive management system for SimRacing championships, results, and statistics, specifically designed for the **Sim Racing Latinoamérica** community. This project consists of a dedicated WordPress plugin and a custom companion theme.

---

## 🏁 Features

- **Championship Management:** Create and manage multiple championships with custom scoring rules.
- **Event Organization:** Organize events within championships, featuring automated point calculation.
- **Result Import:** Support for importing results from popular simulators:
    - **Assetto Corsa:** Single and bulk JSON import.
    - **Automobilista:** Historical data import via specialized Excel (.xlsx) files.
- **Advanced Driver Profiles:** Detailed statistics for each driver, including:
    - Wins, Podiums, Poles, and Fastest Laps.
    - "Hat Tricks" (Pole + Win + Fastest Lap in a single race).
    - Historical result matrices (Wikipedia style).
- **Manual Overrides:** Administrators can manually adjust points, penalties, and finishing statuses (DNF, NC, DQ).
- **Global Statistics:** Automatic aggregation of driver performance across all championships.
- **Localized UI:** Fully translated into Spanish for a seamless community experience.

## 🛠 Technical Implementation

### Plugin: SRL League System
- **Custom Post Types:** Utilizes CPTs for `srl_championship` and `srl_event` for easy content management.
- **Custom Database Tables:** High-performance storage for results (`srl_results`), sessions (`srl_sessions`), and driver global stats (`srl_drivers`).
- **Dynamic Scoring:** Flexible JSON-based scoring system that allows different points structures (F1 style, IndyCar, etc.) per championship.
- **AJAX Driven:** Smooth admin experience for editing results and managing data.

### Theme: SRL Theme
- **Performance Focused:** Minimalist and fast classic WordPress theme.
- **Custom Templates:** Dedicated templates for displaying championship standings and event results.
- **Responsive Design:** Optimized for both desktop and mobile viewing.
- **Brand Integration:** Uses the SRL color palette (Red, Dark Gray, White) and typography.

## 🚀 Version
Current Version: **1.9.2**

---

# 🏎️ SRL League System (Español)

Un sistema integral de gestión de campeonatos, resultados y estadísticas de SimRacing, diseñado específicamente para la comunidad de **Sim Racing Latinoamérica**. Este proyecto consta de un plugin de WordPress dedicado y un tema personalizado.

---

## 🏁 Características

- **Gestión de Campeonatos:** Crea y gestiona múltiples campeonatos con reglas de puntuación personalizadas.
- **Organización de Eventos:** Organiza eventos dentro de los campeonatos con cálculo automatizado de puntos.
- **Importación de Resultados:** Soporte para importar resultados de simuladores populares:
    - **Assetto Corsa:** Importación de archivos JSON (individual o por lotes).
    - **Automobilista:** Importación de datos históricos mediante archivos Excel (.xlsx) especializados.
- **Perfiles de Pilotos Avanzados:** Estadísticas detalladas para cada piloto, incluyendo:
    - Victorias, Podios, Poles y Vueltas Rápidas.
    - "Hat Tricks" (Pole + Victoria + Vuelta Rápida en la misma carrera).
    - Matrices de resultados históricos (estilo Wikipedia).
- **Ajustes Manuales:** Los administradores pueden ajustar manualmente puntos, penalizaciones y estados (DNF, NC, DQ).
- **Estadísticas Globales:** Agregación automática del rendimiento de los pilotos en todos los campeonatos.
- **Interfaz Localizada:** Totalmente traducido al español.

## 🛠 Implementación Técnica

### Plugin: SRL League System
- **Custom Post Types:** Utiliza CPTs para `srl_championship` y `srl_event`.
- **Tablas de Base de Datos Personalizadas:** Almacenamiento optimizado para resultados (`srl_results`), sesiones (`srl_sessions`) y estadísticas de pilotos (`srl_drivers`).
- **Puntuación Dinámica:** Sistema flexible basado en JSON que permite diferentes estructuras de puntos (F1, IndyCar, etc.) por campeonato.
- **Basado en AJAX:** Experiencia de administración fluida para editar resultados y gestionar datos.

### Tema: SRL Theme
- **Enfocado en el Rendimiento:** Tema de WordPress clásico, minimalista y rápido.
- **Plantillas Personalizadas:** Plantillas dedicadas para mostrar las clasificaciones del campeonato y los resultados de los eventos.
- **Diseño Responsivo:** Optimizado para visualización en computadoras y dispositivos móviles.
- **Integración de Marca:** Utiliza la paleta de colores de SRL (Rojo, Gris Oscuro, Blanco) y su tipografía característica.

## 🚀 Versión
Versión Actual: **1.9.2**

---
© 2024 Sim Racing Latinoamérica. Desarrollado por Rafael Leon & Gemini AI.
