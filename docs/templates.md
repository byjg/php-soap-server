---
sidebar_position: 5
---

# Templates Customization

Learn how to customize the service documentation interface using Jinja templates.

## Overview

When you access your SOAP service URL without any query parameters, byjg/soap-server displays a modern, interactive HTML documentation page. This page is rendered using the **byjg/jinja-php** templating engine, and you can fully customize it.

## Default Template

The library includes a beautiful default template with:
- 🎨 Modern, responsive design
- 📱 Mobile-friendly interface
- ⚡ Interactive collapsible operations
- 📋 Copy-to-clipboard functionality
- 🎯 Smooth scrolling navigation
- 🌈 Syntax-highlighted code examples

## Template Location

The default template is located at:
```
templates/service-info.html.jinja
```

## Template Variables

The following variables are available in the template:

### Service Information

| Variable | Type | Description |
|----------|------|-------------|
| `classname` | string | Service name |
| `description` | string | Service description |
| `selfUrl` | string | Current URL (without query string) |
| `warningNamespace` | boolean | Whether to show namespace warning |

### Methods Array

The `methods` array contains operation information:

```jinja
{% for method in methods %}
  {{ method.name }}           {# Operation name #}
  {{ method.description }}    {# Operation description #}
  {{ method.returnTypesStr }} {# Return type as string #}
  {{ method.signatureStr }}   {# Full signature with parameters #}
  {{ method.hasParams }}      {# Boolean: has parameters? #}
  {{ method.exampleRequest }} {# SOAP XML example #}

  {# Parameters array #}
  {% for param in method.params %}
    {{ param.name }}     {# Parameter name #}
    {{ param.type }}     {# Parameter type #}
    {{ param.required }} {# "✅ Yes" or "❌ No" #}
  {% endfor %}
{% endfor %}
```

## Jinja-PHP Syntax

The template uses Jinja-PHP syntax, which is similar to Twig:

### Variables

```jinja
{{ classname }}
{{ method.name }}
```

### Control Structures

```jinja
{% if warningNamespace %}
  <div class="warning">Namespace warning...</div>
{% endif %}

{% for method in methods %}
  <div>{{ method.name }}</div>
{% endfor %}
```

### Filters

Note: Filters require spaces around the pipe `|`:

```jinja
{# Correct - spaces around pipe #}
{{ text | upper }}

{# Wrong - no spaces #}
{{ text|upper }}
```

Available filters:
- `upper` - Convert to uppercase
- `lower` - Convert to lowercase
- `capitalize` - Capitalize words
- `trim` - Remove whitespace
- `length` - Get length
- `default` - Default value

:::warning
**Filter Limitations**

The jinja-php library has limited filter support. For complex formatting, prepare data in PHP before passing to the template.
:::

## Customizing the Template

### Method 1: Edit the Default Template

Simply edit `templates/service-info.html.jinja`:

```jinja
<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ classname }} - My Custom Template</title>
    <style>
        /* Your custom CSS */
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <h1>{{ classname }}</h1>
    <p>{{ description }}</p>

    <h2>Operations</h2>
    {% for method in methods %}
    <div class="operation">
        <h3>{{ method.name }}</h3>
        {% if method.description %}
        <p>{{ method.description }}</p>
        {% endif %}

        <pre>{{ method.signatureStr }}</pre>

        {% if method.hasParams %}
        <table>
            <tr>
                <th>Parameter</th>
                <th>Type</th>
                <th>Required</th>
            </tr>
            {% for param in method.params %}
            <tr>
                <td>{{ param.name }}</td>
                <td>{{ param.type }}</td>
                <td>{{ param.required }}</td>
            </tr>
            {% endfor %}
        </table>
        {% endif %}

        <h4>Returns</h4>
        <p>{{ method.returnTypesStr }}</p>

        <h4>Example Request</h4>
        <pre>{{ method.exampleRequest }}</pre>
    </div>
    {% endfor %}

    <footer>
        <p>Powered by byjg/soap-server</p>
    </footer>
</body>
</html>
```

### Method 2: Create Multiple Templates

You can create different templates for different services. The library currently uses a single template, but you can extend `SoapHandler` to support template selection:

```php
class CustomSoapHandler extends SoapHandler
{
    private string $templateName = 'service-info.html';

    public function setTemplate(string $name): void
    {
        $this->templateName = $name;
    }

    protected function handleINFO(): void
    {
        // Use custom template name
        $templatePath = __DIR__ . '/../templates';
        $loader = new FileSystemLoader($templatePath);
        $template = $loader->getTemplate($this->templateName);
        // ... render template
    }
}
```

## Template Best Practices

:::tip
**Best Practices**

1. **Keep logic in PHP**: Prepare complex data in PHP, not in templates
2. **Use semantic HTML**: Proper HTML structure for accessibility
3. **Mobile-first CSS**: Design for mobile, enhance for desktop
4. **Minimize JavaScript**: Keep templates fast and simple
5. **Test with real data**: Test with actual service operations
6. **Escape output**: Data is already escaped, don't double-escape
:::

## Example: Minimal Template

Here's a simple, minimal template:

```jinja
<!DOCTYPE html>
<html>
<head>
    <title>{{ classname }}</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; }
        h1 { color: #333; }
        .op { border: 1px solid #ddd; padding: 15px; margin: 15px 0; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>{{ classname }}</h1>
    <p>{{ description }}</p>

    <p>
        <a href="{{ selfUrl }}?WSDL">WSDL</a> |
        <a href="{{ selfUrl }}?DISCO">DISCO</a>
    </p>

    <h2>Operations</h2>
    {% for method in methods %}
    <div class="op">
        <h3>{{ method.name }}()</h3>
        <p>{{ method.description }}</p>
        <pre><code>{{ method.returnTypesStr }} {{ method.name }}({{ method.signatureStr }})</code></pre>

        {% if method.hasParams %}
        <h4>Parameters</h4>
        <table>
            <thead>
                <tr><th>Name</th><th>Type</th><th>Required</th></tr>
            </thead>
            <tbody>
                {% for param in method.params %}
                <tr>
                    <td><code>{{ param.name }}</code></td>
                    <td>{{ param.type }}</td>
                    <td>{{ param.required }}</td>
                </tr>
                {% endfor %}
            </tbody>
        </table>
        {% endif %}
    </div>
    {% endfor %}
</body>
</html>
```

## Adding JavaScript Interactivity

You can add custom JavaScript for interactive features:

```html
<script>
// Toggle operation details
function toggleOperation(element) {
    element.classList.toggle('expanded');
}

// Copy to clipboard
function copyCode(button) {
    const code = button.nextElementSibling.textContent;
    navigator.clipboard.writeText(code).then(() => {
        button.textContent = 'Copied!';
        setTimeout(() => {
            button.textContent = 'Copy';
        }, 2000);
    });
}
</script>
```

## Styling with CSS Frameworks

You can use CSS frameworks like Bootstrap or Tailwind:

```jinja
<!DOCTYPE html>
<html>
<head>
    <title>{{ classname }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="display-4">{{ classname }}</h1>
        <p class="lead">{{ description }}</p>

        {% for method in methods %}
        <div class="card mb-3">
            <div class="card-header">
                <h5>{{ method.name }}</h5>
            </div>
            <div class="card-body">
                <p class="card-text">{{ method.description }}</p>
                <!-- More content -->
            </div>
        </div>
        {% endfor %}
    </div>
</body>
</html>
```

## Troubleshooting

### Template Not Found

If you get a "template not found" error:
1. Check the template file exists at `templates/service-info.html.jinja`
2. Verify the `.jinja` extension
3. Check file permissions

### Variables Not Showing

If variables appear blank:
1. Check variable names match exactly
2. Verify data is being passed to `$template->render()`
3. Use `{% if variable %}` to check if variable exists

### Filter Errors

If filters cause errors:
1. Ensure spaces around pipe: `{{ var | filter }}`
2. Use only supported filters
3. Prepare complex formatting in PHP instead

## Next Steps

- [Getting Started](getting-started.md) - Create your first service
- [Using Attributes](using-attributes.md) - Configure services with attributes
- [Complex Types](complex-types.md) - Work with custom classes
