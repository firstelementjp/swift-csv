# FE AI Search

[](https://fe-advanced-search.com/products/)
[](https://www.google.com/search?q=https://wordpress.org/plugins/)
[](https://www.gnu.org/licenses/gpl-2.0.html)

**AI-powered, conversational search for your WordPress site.**

FE AI Search replaces your standard WordPress search with a smart, conversational AI chat. It uses a RAG (Retrieval-Augmented Generation) model to provide accurate answers based _only_ on your website's content, preventing hallucinations.

---

## Overview

This plugin indexes your posts, pages, and custom post types into a custom database table, creating vector embeddings and keyword indexes. When a user asks a question, it finds the most relevant content from your site and uses it to generate a precise, helpful answer.

It supports multiple major AI providers and is built to be highly extensible and performant, even on standard MySQL databases.

## Features

- **Conversational AI Chat UI**: Adds a customizable chat bubble and window (via Shortcode, Block, or PHP function).
- **Multiple AI Providers**: Supports major Generation Models (LLMs) out of the box.
    - OpenAI (GPT-4o mini, etc.)
    - Google (Gemini)
    - Anthropic (Claude)
- **Multiple Embedding Models**: Supports major vectorization models.
    - OpenAI (`text-embedding-3-small`)
    - Google (`text-embedding-004`)
- **Content Synchronization**:
    - **Manual Sync**: A dashboard to build or rebuild the entire search index.
    - **Smart Sync**: Processes only new, updated, or deleted content to save time and API costs.
    - **Real-time Sync**: Automatically indexes new or updated posts the moment they are published.
- **Advanced Japanese Support**:
    - Includes the lightweight `TinySegmenter` for Japanese word segmentation (わかち書き).
    - **MeCab Support**: Automatically detects and uses `MeCab` if installed on the server for superior accuracy.
- **Developer Friendly**: Packed with filter hooks to customize everything from the UI to the tokenizer.

## Pro & Add-Ons

Supercharge your AI search with powerful add-ons:

- **[FE AI Search Pro](https://fe-advanced-search.com/products/fe-ai-search/)**:
    - Advanced model selection (e.g., GPT-4o, Claude 3.5 Sonnet).
    - Support for **custom OpenAI-compatible endpoints** (for `Ollama`, `LocalAI`, etc.).
    - Per-model custom system prompts.
    - Advanced API cost management (rate limiting, notifications).
    - Securely encrypted "Forbidden Words" list.
    - **MCP Server**: Act as a tool for external AI agents.
- **[Analytics Add-on](https://fe-advanced-search.com/products/fe-ai-search-analytics)**:
    - Detailed dashboard of user conversations.
    - "Zero-Hit" keyword tracking to find content gaps.
    - User satisfaction ratings (👍/👎) for AI responses.
    - **System Log Viewer**: A dedicated interface for viewing detailed system logs from Debug Mode.

---

## Installation

### For General Users (Recommended)

1.  Download the latest stable release from the [WordPress.org Plugin Directory](https://wordpress.org/plugins/fe-ai-search/).
2.  Go to your WordPress Admin \> Plugins \> Add New \> Upload Plugin.
3.  Upload the `.zip` file, install, and activate.

### For Developers (from GitHub)

This repository is for development. The `vendor/` directory (containing required libraries) is not included here. You must use Composer to build the plugin.

1.  Clone this repository into your `wp-content/plugins/` directory:
    ```bash
    git clone https://github.com/firstelementjp/fe-ai-search.git
    ```
2.  Navigate into the plugin's directory:
    ```bash
    cd fe-ai-search
    ```
3.  Install the PHP dependencies:
    ```bash
    composer install --no-dev
    ```
4.  Activate the plugin from your WordPress Admin \> Plugins.

---

## Configuration

1.  Go to **FE AI Search \> Settings**.
2.  **API Settings**: Enter your API keys for OpenAI, Google, or Anthropic.
3.  **Sync Options**: Select which post types and content (titles, content, author nicknames) you want to include in the index.
4.  **Run the Indexer**: Go to the **Sync** tab and click **"Rebuild Index"**. This is a mandatory first step to build your search index.

You're all set\! The chat UI will now be available on your site.

---

## For Developers

This plugin is designed to be highly extensible.

### Using MeCab for High-Accuracy Japanese

For superior Japanese search accuracy, we strongly recommend installing `MeCab` and `natto-php`.

1.  Install `MeCab` and `mecab-ipadic-utf8` on your server.
2.  Install `natto-php` via Composer: `composer require codeguy/natto`
3.  The plugin will automatically detect and use `MeCab`. You can verify this in the **Sync** tab ("Japanese Tokenizer Status").

### Key Filter Hooks

- **`fe_ai_search_chat_ui_html`**: Completely override the HTML of the chat UI.
- **`fe_ai_search_system_prompt`**: Modify the base system prompt before it's sent to the AI.
- **`fe_ai_search_tokenize_text`**: Implement your own custom tokenizer for any language.
- **`fe_ai_search_stop_words`**: Add or remove stop words for any language.
- **`fe_ai_search_retrieved_chunks`**: Modify the context chunks _after_ they are retrieved from the database but _before_ they are sent to the AI.

---

## License

**FE AI Search**
Copyright (C) 2025 FirstElement, Inc.
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

### Third-Party Libraries

This plugin incorporates the following third-party libraries:

- **php-stemmer**
    - License: MIT
    - Source: [https://github.com/wamania/php-stemmer](https://github.com/wamania/php-stemmer)
- **TinySegmenter**
    - License: Modified BSD
    - Source: [https://github.com/u7aro/tinysegmenter-php](https://github.com/u7aro/tinysegmenter-php)
    - The full license text is included in the `LICENSE-TinySegmenter.txt` file.
