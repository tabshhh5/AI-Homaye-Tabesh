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
  }
];
