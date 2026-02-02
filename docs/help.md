# 🐛 Troubleshooting

Common issues and solutions.

## General Issues

### Import Fails

- **Cause**: Insufficient memory
- **Solution**:
    - Increase PHP memory_limit
    - Split CSV files

### Character Encoding Issues

- **Cause**: Character code mismatch
- **Solution**:
    - Convert CSV files to UTF-8
    - Specify character code during import

### Large File Processing Issues

- **Cause**: Timeout
- **Solution**:
    - Increase max_execution_time
    - Enable batch processing

## Error Code List

| Code | Description         | Solution              |
| ---- | ------------------- | --------------------- |
| 1001 | File not found      | Check file path       |
| 1002 | Insufficient memory | Increase memory_limit |
| 1003 | File format error   | Check CSV format      |

## Support

If issues persist:

- Report on [GitHub Issues](https://github.com/firstelementjp/swift-csv/issues)
- Attach error logs
