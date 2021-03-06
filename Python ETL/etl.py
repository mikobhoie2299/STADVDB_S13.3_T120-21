# Program Name: pyEL (EL: Extract & Load)
# Author: Oscar Valles
# Date Created: August 1, 2017

# import needed libraries
import petl as etl
import sys
import pymysql
from sqlalchemy import *
import mysql.connector

# handle characters outside of ascii
# reload(sys)
# sys.setdefaultencoding('utf8')

# declare connection properties within dictionary
imdbOriginal = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="imdb_ijs"
)

imdbWarehouse = pymysql.connect(host='localhost',
                                user='root',
                                password='',
                                database='imdb_warehouse')

imdbWarehouse.cursor().execute('SET SQL_MODE=ANSI_QUOTES')

#### Assigning Cursors ####

originalCursor = imdbOriginal.cursor()


#### Assign Original Tables to Variables ####

# movies table
originalCursor.execute('SELECT * FROM movies')
movies = originalCursor.fetchall()
movies = etl.pushheader(movies, ['movie_id', 'name', 'year', 'rank'])

# movies_genres table
originalCursor.execute('SELECT * FROM movies_genres')
movies_genres = originalCursor.fetchall()
movies_genres = etl.pushheader(movies_genres, ['movie_id', 'genre'])

# directors table
originalCursor.execute('SELECT * FROM directors')
directors = originalCursor.fetchall()
directors = etl.pushheader(directors, ['id', 'first_name', 'last_name'])

# directorfullname table
originalCursor.execute('SELECT * FROM directorfullname')
directorfullname = originalCursor.fetchall()
directorfullname = etl.pushheader(directorfullname, ['full_name', 'id'])

# directors_genres table w/0 prob
originalCursor.execute(
    'SELECT director_id, genre FROM directors_genres')
directors_genres = originalCursor.fetchall()
directors_genres = etl.pushheader(
    directors_genres, ['director_id', 'genre'])

# movies_directors table
originalCursor.execute('SELECT * FROM movies_directors')
movies_directors = originalCursor.fetchall()
movies_directors = etl.pushheader(
    movies_directors, ['director_id', 'movie_id'])

# actors table
originalCursor.execute('SELECT * FROM actors')
actors = originalCursor.fetchall()
actors = etl.pushheader(actors, ['id', 'first_name', 'last_name', 'gender'])

# actorfullname table
originalCursor.execute('SELECT * FROM actorfullname')
actorfullname = originalCursor.fetchall()
actorfullname = etl.pushheader(actorfullname, ['full_name', 'id'])

# roles table w/0 role
originalCursor.execute('SELECT movie_id, actor_id FROM roles')
actorIdOnly = originalCursor.fetchall()
actorIdOnly = etl.pushheader(actorIdOnly, ['movie_id', 'actor_id'])


#### Denormalizing Original Tables ####


# Denormalize movies_directors into movies
moviesAndDirectors = etl.join(
    movies, movies_directors, key='movie_id')


# Denormalize roles into movies
moviesAndDirectorsAndRoles = etl.join(
    moviesAndDirectors, actorIdOnly, key='movie_id')


# Add fullname to actors

actors = etl.join(actors, actorfullname, key='id')
# Denormalize roles into actors
""" actorsAndRoles = etl.join(
    actors, actorIdOnly, lkey='id', rkey='actor_id') """


# Add fullname to directors
directors = etl.join(directors, directorfullname, key='id')


# Denormalize movies_directors into directors
directorsAndMovies = etl.join(
    directors, movies_directors, lkey='id', rkey='director_id')


# print(directorsAndGenresAndMovies)
# print(actorsAndRoles)
# print(moviesAndGenresAndDirectorsAndRoles)

# Delete unnecessary columns from all tables
ranks = etl.cut(moviesAndDirectorsAndRoles,
                'movie_id', 'rank', 'director_id', 'actor_id')
movies = etl.cut(moviesAndDirectorsAndRoles,
                 'movie_id', 'name')
directors = etl.cut(directorsAndMovies, 'id', 'full_name')
actors = etl.cut(actors, 'id', 'full_name')

# Rename id to include table name
directors = etl.rename(directors, 'id', 'director_id')
actors = etl.rename(actors, 'id', 'actor_id')

# Remove rows with NULL ranks
ranks = etl.distinct(ranks)
ranks = etl.selectnotnone(ranks, 'rank')

# Remove duplicates after cutting columns
movies = etl.distinct(movies)
directors = etl.distinct(directors)
actors = etl.distinct(actors)


# Insert final tables into data warehouse
etl.todb(ranks, imdbWarehouse, 'ranks')
etl.todb(movies, imdbWarehouse, 'movies')
etl.todb(actors, imdbWarehouse, 'actors')
etl.todb(directors, imdbWarehouse, 'directors')
