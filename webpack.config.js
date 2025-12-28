const path = require('path');

module.exports = [
  // Homa Sidebar (existing)
  {
    entry: './assets/react/index.js',
    output: {
      path: path.resolve(__dirname, 'assets/build'),
      filename: 'homa-sidebar.js',
      library: 'HomaSidebar',
      libraryTarget: 'window'
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env', '@babel/preset-react']
            }
          }
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader']
        }
      ]
    },
    resolve: {
      extensions: ['.js', '.jsx']
    },
    externals: {
      'react': 'React',
      'react-dom': 'ReactDOM'
    }
  },
  // Atlas Dashboard (new)
  {
    entry: './assets/react/atlas-index.js',
    output: {
      path: path.resolve(__dirname, 'assets/build'),
      filename: 'atlas-dashboard.js',
      library: 'AtlasDashboard',
      libraryTarget: 'window'
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env', '@babel/preset-react']
            }
          }
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader']
        }
      ]
    },
    resolve: {
      extensions: ['.js', '.jsx']
    },
    externals: {
      'react': 'React',
      'react-dom': 'ReactDOM'
    }
  },
  // Super Console (PR19)
  {
    entry: './assets/react/super-console-index.js',
    output: {
      path: path.resolve(__dirname, 'assets/build'),
      filename: 'super-console.js',
      library: 'HomaSuperConsole',
      libraryTarget: 'window'
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env', '@babel/preset-react']
            }
          }
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader']
        }
      ]
    },
    resolve: {
      extensions: ['.js', '.jsx']
    },
    externals: {
      'react': 'React',
      'react-dom': 'ReactDOM'
    }
  },
  // Security Center (PR16)
  {
    entry: './assets/react/security-center-index.js',
    output: {
      path: path.resolve(__dirname, 'assets/build'),
      filename: 'security-center.js',
      library: 'HomaSecurityCenter',
      libraryTarget: 'window'
    },
    module: {
      rules: [
        {
          test: /\.(js|jsx)$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env', '@babel/preset-react']
            }
          }
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader']
        }
      ]
    },
    resolve: {
      extensions: ['.js', '.jsx']
    },
    externals: {
      'react': 'React',
      'react-dom': 'ReactDOM'
    }
  }
];
