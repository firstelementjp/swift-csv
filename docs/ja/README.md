# 🚀 Swift CSV

![Banner](https://github.com/firstelementjp/swift-csv/blob/main/assets/images/swift-csv-banner.jpeg?raw=true)

> ローカライズされた管理 UI、自動バッチ処理、開発者向け拡張性を備えた WordPress 用 CSV インポート/エクスポートプラグイン

[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg)](legal.md)
[![Version](https://img.shields.io/badge/version-0.9.9-green.svg)](https://github.com/firstelementjp/swift-csv/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-blue.svg)](https://wordpress.org/)

Swift CSV は、管理画面から投稿データを CSV 形式でインポート/エクスポートする WordPress プラグインです。

## ✨ 特徴

- リアルタイムのインポート/エクスポート進捗表示
- 大規模データセットの自動バッチ処理
- `cf_` プレフィックスのカスタムフィールド対応
- 階層値を含むタクソノミーカラム対応
- 英語ベース文字列と日本語翻訳のローカライズ UI
- 開発者向けフックベースの拡張性
- WordPress フックによる無料版/有料版の分離統合

## 📋 動作環境

- **WordPress**: 6.6 以降
- **PHP**: 8.1 以降
- **メモリ**: 128MB 以上推奨
- **拡張機能**: `mbstring`, `zip`

## 📥 ダウンロード

[Download swift-csv-v0.9.9.zip](https://github.com/firstelementjp/swift-csv/releases/download/v0.9.9/swift-csv-v0.9.9.zip){: .download-btn }

## 🚀 クイックスタート

1. `swift-csv-v0.9.9.zip` をダウンロード
2. WordPress でプラグインをアップロードして有効化
3. **ツール → Swift CSV** を開く
4. まず小さなテストインポート/エクスポートを実行

インポート形式では、`ID` カラムが必須です。新規投稿の場合は空値を使用してください。

## 📖 ドキュメント

- [はじめに](start.md) - クイック導入と基本
- [インストール](install.md) - 詳細なインストール手順
- [設定](config.md) - 設定オプションと構成
- [例](example.md) - 実装例と使用例
- [トラブルシューティング](help.md) - 一般的な問題と解決策
- [開発者向けフック](hooks.md) - 開発者向けリファレンスとカスタマイズ
- [貢献](contribute.md) - 開発ガイドライン
- [変更履歴](changes.md) - バージョン履歴と更新
- [ライセンス](legal.md) - ライセンスと法的情報

## 🤝 貢献

貢献を歓迎します！詳細は[貢献ガイド](contribute.md)をご覧ください。

## 📄 ライセンス

このプラグインは GPL-2.0+ ライセンスで提供されます。詳細は[legal.md](legal.md)をご覧ください。

---

<div style="text-align: center; margin-top: 40px;">
  <p>
    <a href="https://github.com/firstelementjp/swift-csv" target="_blank">🔧 GitHub</a>
  </p>
</div>
