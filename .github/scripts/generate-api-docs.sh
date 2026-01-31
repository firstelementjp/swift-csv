#!/bin/bash

# API documentation generation script
# Generate Markdown format API documentation from PHP files

set -e

# Output directory
OUTPUT_DIR="docs/api"
mkdir -p "$OUTPUT_DIR"

# Header information
cat > "$OUTPUT_DIR/README.md" << 'EOF'
# 📚 API Documentation

Swift CSV plugin API reference.

## Class List

EOF

# Generate API documentation from each PHP file
for file in includes/*.php; do
    if [ -f "$file" ]; then
        classname=$(basename "$file" .php)
        output_file="$OUTPUT_DIR/$(echo "$classname" | tr '[:upper:]' '[:lower:]' | tr '_' '-').md"
        
        echo "Generating docs for $classname..."
        
        # Add class name to README
        echo "- [$classname]($(basename "$output_file"))" >> "$OUTPUT_DIR/README.md"
        
        # Generate class documentation
        cat > "$output_file" << EOF
# $classname

EOF
        
        # Extract class description
        class_desc=$(grep -A 10 "class $classname" "$file" | grep -E "/\*\*|/\*" | head -1 | sed 's/\/\*\*//' | sed 's/\/\*//' | sed 's/\*//' | xargs)
        if [ ! -z "$class_desc" ]; then
            echo "$class_desc" >> "$output_file"
            echo "" >> "$output_file"
        fi
        
        # Method list
        echo "## Methods" >> "$output_file"
        echo "" >> "$output_file"
        
        # Extract public methods
        grep -n "public function" "$file" | while read line; do
            linenum=$(echo "$line" | cut -d: -f1)
            method_line=$(echo "$line" | cut -d: -f2-)
            method=$(echo "$method_line" | sed 's/.*public function //' | sed 's/(.*$//')
            
            # Get method signature
            signature=$(sed -n "${linenum}p" "$file" | sed 's/^[[:space:]]*//')
            
            echo "### $method" >> "$output_file"
            echo "" >> "$output_file"
            echo "\`\`\`php" >> "$output_file"
            echo "$signature" >> "$output_file"
            echo "\`\`\`" >> "$output_file"
            echo "" >> "$output_file"
            
            # Extract PHPDoc comments (lines before method)
            start_line=$((linenum - 10))
            if [ $start_line -lt 1 ]; then start_line=1; fi
            end_line=$((linenum - 1))
            
            # Extract parameters and return values
            phpdoc=$(sed -n "${start_line},${end_line}p" "$file" | grep -E "@param|@return|@throws|@since" | head -10)
            if [ ! -z "$phpdoc" ]; then
                echo "$phpdoc" | while read doc_line; do
                    if [[ "$doc_line" == *"@param"* ]]; then
                        param=$(echo "$doc_line" | sed 's/.*@param //' | sed 's/^[[:space:]]*//')
                        param_name=$(echo "$param" | awk '{print $1}')
                        param_type=$(echo "$param" | awk '{print $2}')
                        param_desc=$(echo "$param" | cut -d' ' -f3-)
                        
                        echo "**Parameters:**" >> "$output_file"
                        echo "- \`$param_name\` ($param_type) - $param_desc" >> "$output_file"
                        echo "" >> "$output_file"
                    elif [[ "$doc_line" == *"@return"* ]]; then
                        return_val=$(echo "$doc_line" | sed 's/.*@return //' | sed 's/^[[:space:]]*//')
                        return_type=$(echo "$return_val" | awk '{print $1}')
                        return_desc=$(echo "$return_val" | cut -d' ' -f2-)
                        
                        echo "**Returns:**" >> "$output_file"
                        echo "- ($return_type) $return_desc" >> "$output_file"
                        echo "" >> "$output_file"
                    elif [[ "$doc_line" == *"@since"* ]]; then
                        since_val=$(echo "$doc_line" | sed 's/.*@since //' | sed 's/^[[:space:]]*//')
                        echo "**Since:** $since_val" >> "$output_file"
                        echo "" >> "$output_file"
                    fi
                done
            fi
            
            echo "---" >> "$output_file"
            echo "" >> "$output_file"
        done
        
        echo "Generated: $output_file"
    fi
done

echo "API documentation generation completed!"
echo "Files generated in: $OUTPUT_DIR/"
