pipeline {
    agent any

    stages {
        stage("Checkout Code") {
            steps {
                echo "Cloning repository from GitHub..."
                checkout scm
            }
        }

        stage("Build Docker Image") {
            steps {
                echo "Building Docker image..."
                bat "docker build -t jacob052205/dan-app:$BUILD_NUMBER ."
            }
        }

        stage("Push Docker Image") {
            steps {
                echo "Pushing Docker image to Docker Hub..."
                withDockerRegistry([credentialsId: "docker-hub-creds", url: ""]) {
                    bat "docker push jacob052205/dan-app:$BUILD_NUMBER"
                }
            }
        }

        stage("Deploy Container") {
            steps {
                echo "Deploying container..."
                bat "docker stop dan-app & exit 0"
                bat "docker rm dan-app & exit 0"
                bat "docker run -d -p 8080:80 --name dan-app jacob052205/dan-app:$BUILD_NUMBER"
            }
        }
    }

    post {
        success {
            echo "Pipeline executed successfully!"
        }
        failure {
            echo "Pipeline failed. Check the logs."
        }
    }
}
