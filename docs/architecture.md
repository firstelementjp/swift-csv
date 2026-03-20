# Architecture Overview

This page describes the current high-level architecture of Swift CSV.

For exact hook names and signatures, see [Developer Hooks](hooks.md).

## System Overview

Swift CSV is organized around a WordPress admin workflow for importing and exporting CSV data.

The major runtime areas are:

- **Admin layer**
  - Registers the admin UI
  - Loads scripts and styles
  - Provides settings and license-related pages

- **Export layer**
  - Builds export headers
  - Collects content in batches
  - Streams progress and logs
  - Generates downloadable CSV output

- **Import layer**
  - Accepts uploaded CSV files
  - Parses and validates rows
  - Maps CSV data into WordPress content
  - Persists posts, custom fields, and taxonomy terms

- **Shared infrastructure**
  - AJAX helpers
  - File utilities
  - General helper utilities
  - Translation assets

## Architectural Goals

The current architecture is shaped by a few practical constraints:

- Avoid PHP timeouts on large datasets
- Keep memory usage stable during import and export
- Allow Free and Pro features to evolve with minimal coupling
- Preserve extensibility through hooks instead of hardcoded integrations
- Keep WordPress-specific behavior isolated behind compatible classes where possible

## Export Flow

### 1. Admin Request and Form Data

The export process starts from the admin UI. JavaScript prepares the request and sends AJAX calls to the export handlers.

### 2. Header Generation

Export headers are composed from three main sources:

- Post fields
- Taxonomy-derived headers
- Custom field headers

This separation allows hooks to target one part of the header pipeline without rewriting the whole process.

### 3. Query Construction

A WordPress query is built from the current export options.

This step is designed to be customizable so projects can:

- Limit exported content
- Adjust post selection rules
- Exclude internal content types

### 4. Chunked Export Processing

Posts are processed in batches instead of one large request.

This helps with:

- Timeout avoidance
- Memory control
- Progress feedback in the admin UI
- Better handling of large datasets

### 5. CSV Assembly and Download

As each batch is processed, the plugin builds the final CSV output and prepares the downloadable file.

## Import Flow

### 1. File Upload and Request Parsing

The import process begins with a CSV upload from the admin interface.

The request parser is responsible for normalizing import options and extracting the inputs needed by the import pipeline.

### 2. CSV Parsing and Header Validation

The CSV parser reads the uploaded data and validates the structure before rows are processed.

Important behavior includes:

- Checking required columns
- Preserving row data for batched processing
- Preparing headers for field mapping

### 3. Row Context Creation

Each row is transformed into a context object or structured context array that contains everything needed for processing.

This is where the system determines things such as:

- Target post type
- Whether the row is a create or update candidate
- Which post fields are allowed
- How taxonomies and custom fields should be handled

### 4. Row Processing

The row processor applies business logic to the current row.

Typical responsibilities include:

- Preparing post data
- Distinguishing insert vs update behavior
- Coordinating with persistence utilities
- Updating counters and logs

### 5. Persistence and Taxonomy Handling

Persistence is separated from request parsing so data storage rules can stay focused and testable.

Taxonomy writing is also separated behind dedicated utilities and interfaces.

This helps keep responsibilities clear between:

- Parsing input
- Deciding behavior
- Writing data

## Batch Processing Model

Both import and export use chunked AJAX processing.

### Why It Exists

Chunked processing helps Swift CSV support large datasets without relying on one long-running request.

### What It Affects

- Progress reporting
- Error recovery
- Cancellation behavior
- Temporary file handling
- Final response assembly

### Practical Implications for Development

When changing import or export code, avoid assumptions such as:

- All data is available in a single request lifecycle
- Progress can be calculated only at the end
- Cleanup runs only on success

## Hook Architecture

Hooks are a first-class extension mechanism in Swift CSV.

Key design patterns include:

- Filtering data before it becomes a CSV header
- Adjusting query arguments before export runs
- Transforming import row data before persistence
- Modifying taxonomy resolution behavior
- Responding to pre- and post-import events

This hook-driven approach also supports Free and Pro interoperability.

## Free and Pro Integration

The Free version provides the base flow and extension points.

The Pro version extends behavior through hooks and compatible implementations instead of rewriting the entire system.

This makes it easier to:

- Share a stable core flow
- Add enhanced features without duplicating large code paths
- Keep feature boundaries explicit

## Interface-Based Design

Some import and export responsibilities are intentionally structured around base classes and interfaces.

Benefits include:

- Easier testing
- More focused classes
- Better separation of concerns
- Safer extension points for future variants

Examples include:

- Base and WordPress-compatible import/export classes
- Taxonomy writer interfaces and implementations
- Dedicated request, parsing, and persistence utilities

## Design Principles

### Single Responsibility

Classes should focus on one major concern where practical.

### Explicit Context Passing

Context arrays and structured arguments are preferred over hidden global assumptions.

### Hooks Should Remain Predictable

Hooks should receive enough context to be useful without forcing consumers to inspect unrelated runtime state.

### WordPress Compatibility First

The plugin is built for WordPress conventions, including:

- AJAX response helpers
- Translation functions
- WordPress data APIs
- Admin UI lifecycle expectations

## Related Documentation

- [Developer Guide](developer.md)
- [Developer Hooks](hooks.md)
- [Configuration](config.md)
- [Troubleshooting](help.md)
- [Contributing](contribute.md)
