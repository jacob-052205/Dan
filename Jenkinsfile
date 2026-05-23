pipeline {
    agent any

    stages {
        stage('Checkout Code') {
            steps {
                echo 'Cloning repository from GitHub...'
                checkout scm
            }
        }

        stage('Deploy with Docker Compose') {
            steps {
                echo 'Starting containers with Docker Compose...'
                bat 'docker-compose down'
                bat 'docker-compose up -d --build'
            }
        }
    }

    post {
        success {
            echo 'Pipeline executed successfully! Apps running at http://localhost:8081'
        }
        failure {
            echo 'Pipeline failed. Check the logs.'
        }
    }
}