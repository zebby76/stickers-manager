group "default" {
  targets = ["prd", "dev"]
}

variable "DOCKER_IMAGE_NAME" {
  default = "zebby76/stickers-manager"
}

variable "DOCKER_IMAGE_TAG" {
  default = "snapshot"
}

variable "DOCKER_IMAGE_LATEST" {
  default = false
}

variable "BASE_IMAGE" {
  default = "docker.io/smalswebtech/base-php"
}

variable "PHP_TAG" {
  default = "8.5"
}

variable "GIT_HASH" {}

function "tag" {
  params = [version, tgt]
  result = [version == "" ? "" : "${DOCKER_IMAGE_NAME}:${version}${tgt == "dev" ? "-dev" : ""}"]
}

target "default" {
  name = "${tgt}"

  matrix = { tgt = ["prd", "dev"] }

  context    = "."
  dockerfile = "Dockerfile"
  target     = "${tgt}"
  platforms  = ["linux/amd64", "linux/arm64"]

  args = {
    BASE_IMAGE = BASE_IMAGE
    PHP_TAG    = PHP_TAG
  }

  labels = {
    "org.opencontainers.image.title"       = "Stickers Manager"
    "org.opencontainers.image.description" = "Football sticker collection manager (Symfony) on base-php."
    "org.opencontainers.image.source"      = "https://github.com/zebby76/stickers-manager"
    "org.opencontainers.image.version"     = DOCKER_IMAGE_TAG
    "org.opencontainers.image.revision"    = GIT_HASH
    "org.opencontainers.image.created"     = timestamp()
  }

  tags = distinct(flatten([
    DOCKER_IMAGE_LATEST ? tag("latest", tgt) : [],
    tag(DOCKER_IMAGE_TAG, tgt),
  ]))
}
